<?php

use App\Support\BandwidthConverter;

test('mbpsToBps converts standard speeds using binary formula with 2% PPPoE overhead', function () {
    // 50 Mbps → 50 × 1.048.576 × 1,02 = 53.477.376 bps
    expect(BandwidthConverter::mbpsToBps(50))->toBe(53477376)
        // 20 Mbps → 20 × 1.048.576 × 1,02 = 21.390.950 bps
        ->and(BandwidthConverter::mbpsToBps(20))->toBe(21390950)
        // 10 Mbps → 10 × 1.048.576 × 1,02 = 10.695.475 bps
        ->and(BandwidthConverter::mbpsToBps(10))->toBe(10695475)
        // 100 Mbps → 100 × 1.048.576 × 1,02 = 106.954.752 bps
        ->and(BandwidthConverter::mbpsToBps(100))->toBe(106954752);
});

test('bpsToMbps converts bps back to Mbps accurately', function () {
    expect(BandwidthConverter::bpsToMbps(53477376))->toBe(51.0)  // 51 MiB karena overhead 2%
        ->and(BandwidthConverter::bpsToMbps(21390950))->toBe(20.4)
        ->and(BandwidthConverter::bpsToMbps(10695475))->toBe(10.2);
});

test('formatHumanReadable formats bps to appropriate units', function () {
    expect(BandwidthConverter::formatHumanReadable(53477376))->toBe('51 Mbps')
        ->and(BandwidthConverter::formatHumanReadable(10695475))->toBe('10.2 Mbps')
        ->and(BandwidthConverter::formatHumanReadable(512000))->toBe('500 Kbps')
        ->and(BandwidthConverter::formatHumanReadable(1073741824))->toBe('1 Gbps');
});
