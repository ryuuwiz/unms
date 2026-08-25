<?php

use App\Models\Odp;
use App\Models\User;
use App\Services\Geospatial\GeoJsonParser;
use App\Services\Geospatial\KmlParser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Setup permission
    Permission::firstOrCreate(['name' => 'odp.lihat']);
    Permission::firstOrCreate(['name' => 'odp.buat']);
    Permission::firstOrCreate(['name' => 'odp.ubah']);
    Permission::firstOrCreate(['name' => 'odp.hapus']);
    Permission::firstOrCreate(['name' => 'pelanggan.lihat']);

    $adminRole = Role::firstOrCreate(['name' => 'admin']);
    $adminRole->syncPermissions(Permission::all());

    $this->user = User::factory()->create();
    $this->user->assignRole($adminRole);
});

test('odp automatically generates default ports on create', function () {
    $odp = Odp::create([
        'nama_odp' => 'ODP-TEST-AUTO-PORT',
        'kapasitas_port' => 8,
        'latitude' => -6.2088,
        'longitude' => 106.8456,
    ]);
    $odp->generateDefaultPorts();

    expect($odp->ports)->toHaveCount(8);
    expect($odp->portTersedia())->toBe(8);
    expect($odp->portTerpakai())->toBe(0);
});

test('kml parser extracts points with auto perumahan and capacity', function () {
    $kmlSample = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <kml xmlns="http://www.opengis.net/kml/2.2">
      <Document>
        <Folder>
          <name>Cluster Lavender</name>
          <Placemark>
            <name>ODP-LAV-01 (1:16)</name>
            <description>Kapasitas: 16 port dekat gerbang</description>
            <Point>
              <coordinates>106.845600,-6.208800,0</coordinates>
            </Point>
          </Placemark>
        </Folder>
        <Placemark>
          <name>Area Coverage Lavender</name>
          <Polygon>
            <outerBoundaryIs>
              <LinearRing>
                <coordinates>
                  106.840,-6.200,0
                  106.850,-6.200,0
                  106.850,-6.210,0
                  106.840,-6.210,0
                  106.840,-6.200,0
                </coordinates>
              </LinearRing>
            </outerBoundaryIs>
          </Polygon>
        </Placemark>
      </Document>
    </kml>
    XML;

    $parser = new KmlParser;
    $result = $parser->parse($kmlSample);

    expect($result['points'])->toHaveCount(1);
    expect($result['points'][0]['nama'])->toBe('ODP-LAV-01 (1:16)');
    expect($result['points'][0]['perumahan'])->toBe('Cluster Lavender');
    expect($result['points'][0]['kapasitas'])->toBe(16);
    expect($result['points'][0]['latitude'])->toBe(-6.2088);
    expect($result['points'][0]['longitude'])->toBe(106.8456);

    expect($result['polygons'])->toHaveCount(1);
    expect($result['polygons'][0]['nama'])->toBe('Area Coverage Lavender');
});

test('geojson parser extracts points with auto perumahan and capacity', function () {
    $geoJsonSample = json_encode([
        'type' => 'FeatureCollection',
        'name' => 'Perumahan Griya Asri',
        'features' => [
            [
                'type' => 'Feature',
                'properties' => [
                    'name' => 'ODP-GRIYA-01',
                    'capacity' => 16,
                    'cluster' => 'Griya Asri Blok A',
                ],
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [106.8500, -6.2100],
                ],
            ],
        ],
    ]);

    $parser = new GeoJsonParser;
    $result = $parser->parse($geoJsonSample);

    expect($result['points'])->toHaveCount(1);
    expect($result['points'][0]['nama'])->toBe('ODP-GRIYA-01');
    expect($result['points'][0]['perumahan'])->toBe('Griya Asri Blok A');
    expect($result['points'][0]['kapasitas'])->toBe(16);
    expect($result['points'][0]['latitude'])->toBe(-6.2100);
    expect($result['points'][0]['longitude'])->toBe(106.8500);
});

test('api maps markers endpoint returns lightweight json structure', function () {
    $this->actingAs($this->user);

    Odp::create([
        'nama_odp' => 'ODP-API-TEST',
        'kapasitas_port' => 8,
        'latitude' => -6.2000,
        'longitude' => 106.8400,
    ]);

    $response = $this->getJson(route('api.maps.markers', ['layers' => 'odp']));

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'total_markers',
            'markers' => [
                '*' => [
                    'id',
                    'layer',
                    'lat',
                    'lng',
                    'title',
                    'subtitle',
                    'badge',
                    'color',
                    'detail_url',
                    'icon',
                ],
            ],
            'polygons',
        ]);
});

test('odp scope terdekat finds nearest odps with straight distance', function () {
    Odp::create([
        'nama_odp' => 'ODP-DEKAT',
        'kapasitas_port' => 8,
        'latitude' => -6.2001,
        'longitude' => 106.8401,
    ]);

    Odp::create([
        'nama_odp' => 'ODP-JAUH',
        'kapasitas_port' => 8,
        'latitude' => -6.2500,
        'longitude' => 106.8900,
    ]);

    $nearest = Odp::query()
        ->terdekat(-6.2000, 106.8400)
        ->get();

    expect($nearest)->not->toBeEmpty();
    expect($nearest->first()->nama_odp)->toBe('ODP-DEKAT');
});
