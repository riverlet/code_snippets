<?php
/**
 * 基于 Yii 1.x 的多进程任务处理框架
 * Created by PhpStorm.
 *
 * @author riverlet<mr.riverlet@gmail.com>
 * @website http://riverlet.me
 */


declare(ticks=1);

class MPCommand extends CConsoleCommand {

    private $pidFile = '/var/run/yiic_mpcommand.pid';
    private $pids = array();


    /**
     * 获取任务，此处一般是从队列中提取一项任务
     * @return int
     */
    protected function getTask() {
        return time();
    }

    /**
     * 处理任务，即交给子进程处理的具体业务逻辑入口
     * @param $task 任务对象或任务ID
     */
    protected function processTask($task) {
        echo "Task #{$task} is being processed.";
    }

    /**
     * 启动队列
     * @param int $proc 最大进程数
     * @return int
     */
    public function actionStart($proc = 5) {
        return $this->getAndProcess($proc);
    }

    /**
     * 启动单个任务处理，可用于测试
     */
    public function actionStartSingle() {
        $task = $this->getTask();
        if(empty($task)) {
            echo "Task not found.\n";
        } else {

            $this->processTask($task);

            Yii::app()->end();
        }
    }

    /**
     * 停止队列
     * @return int
     */
    public function actionStop() {
        if(file_exists($this->pidFile)) {
            exec('kill '.intval(file_get_contents($this->pidFile)));
            return 0;
        } else {
            printf("Error: PID file %s could NOT be found.\nYou can kill me manually.\n", $this->pidFile);
            return 1;
        }
    }



    /**
     * 主程序，分发进程并处理任务
     * @param int $proc
     */
    private function getAndProcess($proc = 5) {
        $this->daemonize();

        //-- Check parameters ----------
        if(!is_numeric($proc) || $proc > 1000 || $proc < 1) {
            echo "Wrong param: --proc\n";
            exit(1);
        }


        //-- Here we go ------------------
        $processNum = intval($proc);

        while(1) {

            if(count($this->pids) <= $processNum) {
                $taskId = $this->getTask(); //Yii::app()->queue->dequeue();

                if(empty($taskId)) {
                    sleep(3); //
                } else {
                    $pid = pcntl_fork();
                    if($pid === 0) {

                        $this->processTask($taskId);//(Task::checkOut($taskId));

                        Yii::app()->end();

                    } else {
                        $this->pids[] = $pid;
                    }
                }

            }



            $finished = pcntl_waitpid(-1, $status, WNOHANG);
            while($finished > 0) {
                unset($this->pids[array_search($finished, $this->pids)]);
                $finished = pcntl_waitpid(-1, $status, WNOHANG);
            }

        }


        return 0;
    }


    /**
     * 派生后台子进程，主进程退出。
     */
    private function daemonize() {

        if(file_exists($this->pidFile)) {
            printf("Existing PID file found during start. \nIt appears to still be running with PID %d.\nStart aborted.\n", file_get_contents($this->pidFile));
            die(1);
        }

        $this->pids = array();

        //Daemonize
        $pid = pcntl_fork();
        if($pid) {
            file_put_contents($this->pidFile, $pid);
            exit();
        }

        // register signal handlers
        pcntl_signal(SIGTERM, array($this, "signalHandler"));
        pcntl_signal(SIGHUP, array($this, "signalHandler"));
        pcntl_signal(SIGINT, array($this, "signalHandler"));
        pcntl_signal(SIGUSR1, array($this, "signalHandler"));

        register_shutdown_function(array($this, "shutdown"));
    }

    public function signalHandler($sigNo) {

        if($sigNo == SIGTERM || $sigNo == SIGHUP || $sigNo == SIGINT) {

            foreach($this->pids as $p) {
                posix_kill($p, $sigNo);
            }

            foreach($this->pids as $p) {
                pcntl_waitpid($p, $status);
            }

            exit();

        } elseif ($sigNo == SIGUSR1) {
            echo "I currently have ". count($this->pids) ." children\n";
        }

    }

    /**
     * 脚本停止时的操作
     */
    public function shutdown() {
        if (getmypid() == file_get_contents($this->pidFile)) {
            Yii::trace('Shuting down #'.getmypid());
            @unlink($this->pidFile);
        }
    }

    /**
     * 初始化，该函数会被Yii自动调用
     */
    public function init() {
        $this->pidFile = Yii::getPathOfAlias('app').DIRECTORY_SEPARATOR.'yiic_mpcommand.pid';

        Yii::getLogger()->autoDump = true;
        Yii::getLogger()->autoFlush = 1;
    }
} 