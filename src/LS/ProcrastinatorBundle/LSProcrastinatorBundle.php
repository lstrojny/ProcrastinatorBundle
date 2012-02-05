<?php
namespace LS\ProcrastinatorBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Kernel;

class LSProcrastinatorBundle extends Bundle
{
    private $kernel;

    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    public function shutdown()
    {
        $procrastinator = $this->kernel
                               ->getContainer()
                               ->get('procrastinator');
        $procrastinator->schedule();
    }
}