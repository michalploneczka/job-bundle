<?php
/*
* This file is part of the job-bundle package.
*
* (c) Hannes Schulz <hannes.schulz@aboutcoders.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Abc\Bundle\JobBundle\Serializer\Handler;

use Abc\Bundle\JobBundle\Job\JobParameterArray;
use Abc\Bundle\JobBundle\Job\JobTypeRegistry;
use JMS\Serializer\Context;
use JMS\Serializer\Exception\RuntimeException;
use JMS\Serializer\GraphNavigator;
use JMS\Serializer\Handler\SubscribingHandlerInterface;
use JMS\Serializer\VisitorInterface;

/**
 * @author Hannes Schulz <hannes.schulz@aboutcoders.com>
 */
class JobParameterArrayHandler implements SubscribingHandlerInterface
{
    /**
     * @var JobTypeRegistry
     */
    private $registry;

    /**
     * @param JobTypeRegistry $registry
     */
    public function __construct(JobTypeRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribingMethods()
    {
        $formats = ['json'];
        $methods = [];

        foreach ($formats as $format) {
            $methods[] = [
                'type'      => JobParameterArray::class,
                'direction' => GraphNavigator::DIRECTION_SERIALIZATION,
                'format'    => $format,
                'method'    => 'serializeJobParameterArray'
            ];

            $methods[] = [
                'type'      => JobParameterArray::class,
                'direction' => GraphNavigator::DIRECTION_DESERIALIZATION,
                'format'    => $format,
                'method'    => 'deserializeJobParameterArray'
            ];
        }

        return $methods;
    }

    /**
     * @param VisitorInterface $visitor
     * @param array            $data
     * @param array            $type
     * @param Context          $context
     * @return mixed
     */
    public function serializeJobParameterArray(VisitorInterface $visitor, array $data, array $type, Context $context)
    {
        return $visitor->visitArray($data, $type, $context);
    }

    /**
     * @param VisitorInterface $visitor
     * @param mixed            $data
     * @param array            $type
     * @param Context          $context
     * @return array|null
     * @throws RuntimeException If $data contains more elements than $type['params']
     */
    public function deserializeJobParameterArray(VisitorInterface $visitor, $data, array $type, Context $context)
    {
        /**
         * If $type['params'] is not set this most likely means, that a job is being deserialized, so we check if the JobDeserializationSubscriber set the type of params at the end of the $data array
         *
         * @see JobDeserializationSubscriber::onPreDeserialize()
         */
        $deserializeJob = false;
        if (count($type['params']) == 0 && is_array($data) && is_array(end($data)) && in_array('abc.job.type', array_keys(end($data)))) {

            $jobType        = $this->extractJobType($data);
            $type['params'] = $this->getParamTypes($jobType);
            $deserializeJob = true;
        }

        if (is_array($data) && count($data) > count($type['params'])) {
            throw new RuntimeException(sprintf('Invalid job parameters, the parameters contain more elements that defined (%s)', implode(',', $type['params'])));
        }

        $result = [];
        for ($i = 0; $i < count($type['params']); $i++) {
            if (!is_array($data) || !isset($data[$i]) || null == $data[$i]) {
                $result[$i] = null;
            } else {
                if (!is_array($type['params'][$i])) {
                    $type['params'][$i] = [
                        'name'   => $type['params'][$i],
                        'params' => array()
                    ];
                }

                $result[$i] = $context->accept($data[$i], $type['params'][$i]);
            }
        }

        if (count($data) > 0 && !$deserializeJob) {
            /**
             * Since serializer always returns the result of $context->accept unless visitor result is empty,
             * we have to make sure that the visitor result is null in case only root is type JobParameterArray::class
             *
             * @see Serializer::handleDeserializeResult()
             */
            $visitor->setNavigator($context->getNavigator());
        }

        return $result;
    }

    /**
     * Extracts the job type from the array
     *
     * @param array $data
     * @return string
     */
    private function extractJobType(&$data)
    {
        $jobTypeArray = array_pop($data);

        return array_pop($jobTypeArray);
    }

    /**
     * @param string $type The job type
     * @return array
     */
    private function getParamTypes($type)
    {
        $jobType = $this->registry->get($type);
        $types   = $jobType->getParameterTypes();
        $indices = $jobType->getIndicesOfSerializableParameters();

        $rs = array();
        foreach ($indices as $index) {
            $rs[] = $types[$index];
        }

        return $rs;
    }
}