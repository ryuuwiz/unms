<?php

namespace App\Support;

class BurstProfileCalculator
{
  public const BURST_RATE_MULTIPLIER = 1.5;

  public const BURST_THRESHOLD_MULTIPLIER = 0.8;

  public const BURST_TIME_SECONDS = 16;

  /**
   * @return array{burst_rate_tx: int|null, burst_rate_rx: int|null, burst_threshold_tx: int|null, burst_threshold_rx: int|null, burst_time_tx: int|null, burst_time_rx: int|null}
   */
  public static function calculate(?int $maxLimitTx, ?int $maxLimitRx): array
  {
    return [
      'burst_rate_tx' => self::burstRate($maxLimitTx),
      'burst_rate_rx' => self::burstRate($maxLimitRx),
      'burst_threshold_tx' => self::burstThreshold($maxLimitTx),
      'burst_threshold_rx' => self::burstThreshold($maxLimitRx),
      'burst_time_tx' => $maxLimitTx === null ? null : self::BURST_TIME_SECONDS,
      'burst_time_rx' => $maxLimitRx === null ? null : self::BURST_TIME_SECONDS,
    ];
  }

  private static function burstRate(?int $maxLimit): ?int
  {
    if ($maxLimit === null) {
      return null;
    }

    return max($maxLimit + 1, (int) ceil($maxLimit * self::BURST_RATE_MULTIPLIER));
  }

  private static function burstThreshold(?int $maxLimit): ?int
  {
    if ($maxLimit === null) {
      return null;
    }

    return max(1, (int) floor($maxLimit * self::BURST_THRESHOLD_MULTIPLIER));
  }
}
