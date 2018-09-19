<?php
namespace BehEh\Flaps\Throttling;

use BehEh\Flaps\ThrottlingStrategyInterface;
use InvalidArgumentException;
use BehEh\Flaps\StorageInterface;
use LogicException;

/**
 * This simplifies implementation of a single time-based lock
 *
 * @since 0.1
 * @author Abhijit Kane <abhijitkane@gmail.com>
 */
class LockStrategy implements ThrottlingStrategyInterface
{
	/**
	 * @var float
	 */
	protected $timeSpan;

	/**
	 * Sets the timespan in which the defined number of requests is allowed per single entity.
	 * @param float|string $timeSpan
	 * @throws InvalidArgumentException
	 */
	public function setTimeSpan($timeSpan)
	{
		if (is_string($timeSpan)) {
			$timeSpan = self::parseTime($timeSpan);
		}
		if (!is_numeric($timeSpan)) {
			throw new InvalidArgumentException('timespan is not numeric');
		}
		$timeSpan = floatval($timeSpan);
		if ($timeSpan <= 0) {
			throw new InvalidArgumentException('timespan cannot be 0 or less');
		}
		$this->timeSpan = $timeSpan;
	}

	/**
	 * Returns the previously set timespan.
	 * @return float
	 */
	public function getTimeSpan()
	{
		return (float) $this->timeSpan;
	}

	/**
	 * Sets the lock for $lockDuration
	 * @param int|string $lockDuration tither the amount of seconds or a string such as "10s", "5m" or "1h"
	 * @throws InvalidArgumentException
	 * @see LeakyBucketStrategy::setTimeSpan
	 */
	public function __construct($lockDuration)
	{
		$this->setTimeSpan($lockDuration);
	}

	/**
	 * @var StorageInterface
	 */
	protected $storage;

	public function setStorage(StorageInterface $storage)
	{
		$this->storage = $storage;
	}

	/**
	 * Parses a timespan string such as "10s", "5m" or "1h" and returns the amount of seconds.
	 * @param string $timeSpan the time span to parse to seconds
	 * @return float|null the number of seconds or null, if $timeSpan couldn't be parsed
	 */
	public static function parseTime($timeSpan)
	{
		$times = array('s' => 1, 'm' => 60, 'h' => 3600, 'd' => 86400, 'w' => 604800);
		$matches = array();
		if (is_numeric($timeSpan)) {
			return $timeSpan;
		}
		if (preg_match('/^((\d+)?(\.\d+)?)('.implode('|', array_keys($times)).')$/',
				$timeSpan, $matches)) {
			return floatval($matches[1]) * $times[$matches[4]];
		}
		return null;
	}

	/**
	 * Returns whether entity exceeds its allowed request capacity with this request.
	 * @param string $identifier the identifer of the entity to check
	 * @return bool true if this requests exceeds the number of requests allowed
	 * @throws LogicException if no storage has been set
	 */
	public function isViolator($identifier)
	{	
		if ($this->storage === null) {
			throw new LogicException('no storage set');
		}

		$toCountOverflows = true;

		$time = microtime(true);
		$timestamp = $time;
		$toBlock = false;

		$timeSpan = $this->timeSpan;
		$isLockSet = $this->storage->getValue($identifier);

		if($isLockSet == 0) {
			// no lock is set yet
			// set the lock and return false
			$this->storage->setValue($identifier, 1);
			$this->storage->setTimestamp($identifier, $timestamp);
			$this->storage->expireIn($identifier, $this->timeSpan);
			return false;
		}
		return true;
	}
}
