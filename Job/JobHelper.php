<?php
/*
* This file is part of the job-bundle package.
*
* (c) Hannes Schulz <hannes.schulz@aboutcoders.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Abc\Bundle\JobBundle\Job;

use Abc\Bundle\JobBundle\Job\Logger\FactoryInterface;
use Abc\Bundle\JobBundle\Model\JobInterface as EntityJobInterface;
use Psr\Log\LoggerInterface;

/**
 * Helper class to manage jobs.
 *
 * @author Hannes Schulz <hannes.schulz@aboutcoders.com>
 */
class JobHelper
{
    /** @var FactoryInterface */
    protected $loggerFactory;

    /**
     * @param FactoryInterface $loggerFactory
     */
    function __construct(FactoryInterface $loggerFactory)
    {
        $this->loggerFactory   = $loggerFactory;
    }

    /**
     * Updates a job.
     *
     * If the given status is ERROR or CANCELLED the child jobs of the given job will be terminated with status CANCELLED.
     *
     * @param EntityJobInterface $job
     * @param Status            $status
     * @param int               $processingTime
     * @param mixed|null        $response
     */
    public function updateJob(EntityJobInterface $job, Status $status, $processingTime = 0, $response = null)
    {
        $job->setStatus($status);
        $job->setProcessingTime($job->getProcessingTime() + ($processingTime === null ? 0 : $processingTime));
        $job->setResponse($response);

        if(in_array($status->getValue(), Status::getTerminatedStatusValues()))
        {
            $job->setTerminatedAt(new \DateTime());
        }

        if($job->hasSchedules() && in_array($status->getValue(), Status::getTerminatedStatusValues()))
        {
            foreach($job->getSchedules() as $schedule)
            {
                if(method_exists($schedule, 'setIsActive'))
                {
                    $schedule->setIsActive(false);
                }
            }
        }
    }

    /**
     * Calculates the processing time
     *
     * @param float $executionStart The microtime when the execution was started
     * @return double The processing time
     */
    public function calculateProcessingTime($executionStart)
    {
        return (double)microtime(true) - $executionStart;
    }

    /**
     * @param EntityJobInterface $job
     * @return LoggerInterface
     */
    public function getJobLogger(EntityJobInterface $job)
    {
        return $this->loggerFactory->create($job);
    }
}