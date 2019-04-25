<?php
// +----------------------------------------------------------------------
// | msfoole [ 基于swoole4的高性能API服务框架 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018 http://julibo.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: carson <yuzhanwei@aliyun.com>
// +----------------------------------------------------------------------

namespace Julibo\Msfoole\Server;

use Swoole\Process;
use Swoole\Table;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Websocket\Server as Websocket;
use Swoole\WebSocket\Frame as Webframe;
use Julibo\Msfoole\Facade\Config;
use Julibo\Msfoole\Facade\Cache;
use Julibo\Msfoole\Facade\Log;
use Julibo\Msfoole\Facade\Cookie;
use Julibo\Msfoole\Channel;
use Julibo\Msfoole\Helper;
use Julibo\Msfoole\HttpClient;
use Julibo\Msfoole\Application;
use Julibo\Msfoole\WebSocket as SocketApp;
use Julibo\Msfoole\Interfaces\Server as BaseServer;

class HttpServer extends BaseServer
{
    /**
     * SwooleServer类型
     * @var string
     */
    protected $serverType = 'http';

    /**
     * 支持的响应事件
     * @var array
     */
    protected $event = [
        'Start',
        'Shutdown',
        'ManagerStart',
        'ManagerStop',
        'WorkerStart',
        'WorkerStop',
        'WorkerExit',
        'WorkerError',
        'Close',
        'Request',
        'Open',
        'Message',
    ];

    /**
     * @var 通道
     */
    protected $chan;

    /**
     * 客户端连接内存表
     */
    protected $table;

    /**
     * @var 应用名
     */
    protected $appName;

    /**
     * @var string 健康检查IP
     */
    protected $health_host;

    /**
     * @var 健康检查计数器
     */
    protected $counter = 0;

    /**
     * @var string 健康检查URI
     */
    protected $health_uri;

    /**
     * @var 健康检查许可证
     */
    protected $health_permit;

    /**
     * 健康检查开关
     * @var bool
     */
    protected $health_switch = false;

    /**
     * 健康检查极限值
     * @var int
     */
    protected $health_limit = 10;

    /**
     * 初始化
     */
    protected function init()
    {
        $this->appName = Config::get('application.name') ?? '';
        $this->option['upload_tmp_dir'] = TEMP_PATH;
        $this->option['http_parse_post'] = true;
        $this->option['http_compression'] = true;
        $config = Config::get('msfoole') ?? [];
        unset($config['host'], $config['port'], $config['ssl'], $config['option']);
        $this->config = array_merge($this->config, $config);
        $this->health_switch = $this->config['health']['switch'] ?? false;
        $this->health_host = $this->config['health']['host'] ?? '127.0.0.1';
        $this->health_uri = $this->config['health']['uri'] ?? '/Index/Index/health';
        $this->health_permit = $this->config['health']['permit'] ?? '700040d41f47592c';
        $this->health_limit = $this->config['health']['limit'] ?? 10;
        if ($this->pattern) {
            $this->serverType = 'socket';
        }
    }

    /**
     * 启动辅助逻辑
     */
    protected function startLogic()
    {
        # 初始化缓存
        $cacheConfig = Config::get('cache.default') ?? [];
        Cache::init($cacheConfig);
        # 开启异步定时监控
        $this->monitorProcess();
        # 创建客户端连接内存表
        $this->createTable();
    }

    /**
     * 创建客户端连接内存表
     */
    private function createTable()
    {
        if ($this->serverType == 'socket') {
            $this->table = new table($this->config['table']['size'] ?? 1024);
            $this->table->column('token', table::TYPE_STRING, 32); // 用户标识符
            $this->table->column('counter', table::TYPE_INT, 4); // 计数器
            $this->table->column('create_time', table::TYPE_INT, 4); // 创建时间戳
            $this->table->column('update_time', table::TYPE_INT, 4); // 更新时间戳
            $this->table->column('user', table::TYPE_STRING, 1024); // 用户信息描述
            $this->table->create();
        }
    }

    /**
     * 文件监控，不包含配置变化
     */
    private function monitorProcess()
    {
        $monitorConfig = $this->config['monitor'] ?? [];
        $switch = $monitorConfig['switch'] ?? false;
        $paths = $monitorConfig['path'] ?? null;
        if ($switch && $paths) {
            $monitor = new Process(function (Process $process) use ($monitorConfig) {
                // echo "文件监控进程启动";
                Helper::setProcessTitle("msfoole:monitor-" . $this->appName);
                $timer = $monitorConfig['interval'] ?? 3;
                $paths = $monitorConfig['path'];
                swoole_timer_tick($timer * 1000, function () use($paths) {
                    if (!is_array($paths)) {
                        $paths = array($paths);
                    }
                    foreach ($paths as $path) {
                        $path = ROOT_PATH . $path;
                        if (!is_dir($path)) {
                            continue;
                        }
                        $dir      = new \RecursiveDirectoryIterator($path);
                        $iterator = new \RecursiveIteratorIterator($dir);
                        foreach ($iterator as $file) {
                            if (pathinfo($file, PATHINFO_EXTENSION) != 'php') {
                                continue;
                            }
                            if ($this->lastMtime < $file->getMTime()) {
                                $this->lastMtime = $file->getMTime();
                                echo '[update]' . $file . " reload...\n";
                                $this->swoole->reload();
                                break 2;
                            }
                        }
                    }
                });
            });
            $this->swoole->addProcess($monitor);
        }
    }

    /**
     * @param \Swoole\Server $server
     */
    public function onStart(\Swoole\Server $server)
    {
        // echo "主进程启动";
        Helper::setProcessTitle("msfoole:master-" . $this->appName);
    }

    /**
     * @param \Swoole\Server $server
     */
    public function onShutdown(\Swoole\Server $server)
    {
        // echo "主进程结束";
        $tips = sprintf("【%s:%s】主进程结束", $this->host, $this->port);
        Helper::sendDingRobotTxt($tips);
    }

    /**
     * @param \Swoole\Server $server
     */
    public function onManagerStart(\Swoole\Server $server)
    {
        // echo "管理进程启动";
        Helper::setProcessTitle("msfoole:manager-" . $this->appName);
    }

    /**
     * @param \Swoole\Server $server
     */
    public function onManagerStop(\Swoole\Server $server)
    {
        // echo "管理进程停止";
        $tips = sprintf("【%s:%s】管理进程停止", $this->host, $this->port);
        Helper::sendDingRobotTxt($tips);
    }

    /**
     * @param \Swoole\Server $server
     * @param int $worker_id
     */
    public function onWorkerStop(\Swoole\Server $server, int $worker_id)
    {
        // echo "worker进程终止";
        $tips = sprintf("【%s:%s】worker进程终止", $this->host, $this->port);
        Helper::sendDingRobotTxt($tips);
    }

    /**
     * @param \Swoole\Server $server
     * @param int $worker_id
     */
    public function onWorkerExit(\Swoole\Server $server, int $worker_id)
    {
        // echo "worker进程退出";
        $tips = sprintf("【%s:%s】worker进程退出", $this->host, $this->port);
        Helper::sendDingRobotTxt($tips);
    }

    /**
     * @param \Swoole\Server $serv
     * @param int $worker_id
     * @param int $worker_pid
     * @param int $exit_code
     * @param int $signal
     */
    public function onWorkerError(\Swoole\Server $serv, int $worker_id, int $worker_pid, int $exit_code, int $signal)
    {
        $error = sprintf("【%s:%s】worker进程异常:[%d] %d 退出的状态码为%d, 退出的信号为%d", $this->host, $this->port, $worker_pid, $worker_id, $exit_code, $signal);
        Helper::sendDingRobotTxt($error);
    }

    /**
     * @param \Swoole\Server $server
     * @param int $fd
     * @param int $reactorId
     */
    public function onClose(\Swoole\Server $server, int $fd, int $reactorId)
    {
        // echo sprintf('%s的连接关闭', $fd);
        // 销毁客户连接内存表记录
        if (!is_null($this->table) && $this->table->exist($fd)) {
            $this->table->del($fd);
        }
    }

    /**
     * Worker进程启动回调
     * @param \Swoole\Server $server
     * @param int $worker_id
     */
    public function onWorkerStart(\Swoole\Server $server, int $worker_id)
    {
        // echo "worker进程启动";
        Helper::setProcessTitle("msfoole:worker-" . $this->appName);
        // step 0 健康检查
        if ($worker_id == 0 && $this->health_switch) {
            swoole_timer_tick(1000, function () use($server) {
                $cli = new HttpClient($this->health_host, $this->port, $this->health_permit);
                $result = $cli->get($this->health_uri);
                if (empty($result) || empty($result['statusCode']) || $result['statusCode'] != 200) {
                    $this->counter++;
                } else {
                    $this->counter = 0;
                }
                if ($this->counter > $this->health_limit) {
                    $server->shutdown();
                }
            });
        }
        // step 1 创建通道
        $chanConfig = $this->config['channel'];
        $capacity = $chanConfig['capacity'] ?? 100;
        $this->chan = Channel::instance($capacity);
        // step 2 开启日志通道
        Log::setChan($this->chan);
        // step 3 创建协程工作池
        $this->WorkingPool();
        // 初始化Cookie对象
        $cookieConf = Config::get('cookie') ?? [];
        Cookie::init($cookieConf);
    }

    /**
     * 工作协程
     * 负责从通道中消费数据并进行异步处理
     */
    private function WorkingPool()
    {
        go(function () {
            while(true) {
                $data = $this->chan->pop();
                if (!empty($data) && is_int($data['type'])) {
                    switch ($data['type']) {
                        case 2:
                            // 执行自定义方法
                            if ($data['class'] && $data['method']) {
                                $parameter = $data['parameter'] ?? [];
                                call_user_func_array([$data['class'], $data['method']], $parameter);
                            }
                            break;
                        default:
                            // 写入日志记录
                            if (!empty($data['log'])) {
                                Log::saveData($data['log']);
                            }
                            break;
                    }
                }
            }
        });
    }

    /**
     * request回调
     * @param SwooleRequest $request
     * @param SwooleResponse $response
     * @throws \Throwable
     */
    public function onRequest(SwooleRequest $request, SwooleResponse $response)
    {
        // 执行应用并响应
        // print_r($request);
        $uri = $request->server['request_uri'];
        if ($uri == '/favicon.ico') {
            $response->status(404);
            $response->end();
        } else {
            # 服务器端处理跨域
            if (isset($request->header['origin'])) {
                $origin = true;
                if (is_array(Config::get('application.access.origin'))) {
                    in_array($request->header['origin'], Config::get('application.access.origin')) ? : $origin = false;
                } else {
                    $request->header['origin'] == Config::get('application.access.origin') ? : $origin = false;
                }
                if ($origin) {
                    // $response->header('Access-Control-Allow-Origin', '*');
                    $response->header('Access-Control-Allow-Origin', $request->header['origin']);
                    $response->header('Access-Control-Allow-Credentials', 'true');
                    $response->header('Access-Control-Max-Age', '3600');
                    $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Cookie, token, timestamp, level, signer, identification');
                    $response->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
                }
            }
            if ($request->server['request_method'] == 'OPTIONS') {
                $response->status(200);
                $response->end();
            } else {
                $app = new Application($request, $response);
                $app->handling();
            }
        }
    }

    /**
     * 连接开启回调
     * @param Websocket $server
     * @param SwooleRequest $request
     */
    public function WebsocketonOpen(Websocket $server, SwooleRequest $request)
    {
        // 开启websocket连接
        print_r($request);
        $application = new SocketApp($this->table, $this->chan);
        $application->open($server, $request);
    }

    /**
     * Message回调
     * @param Websocket $server
     * @param Webframe $frame
     * @throws \Throwable
     */
    public function WebsocketonMessage(Websocket $server, Webframe $frame)
    {
        // 执行应用并响应
        print_r("receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}");
        $application = new SocketApp($this->table, $this->chan);
        $application->handling($server, $frame);
    }

}
