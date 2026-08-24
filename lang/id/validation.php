<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Baris Bahasa untuk Validasi
    |--------------------------------------------------------------------------
    |
    | Baris bahasa berikut berisi pesan standar penanganan error validasi
    | yang digunakan oleh kelas validator di Laravel.
    |
    */

    'accepted' => ':attribute harus diterima.',
    'accepted_if' => ':attribute harus diterima jika :other adalah :value.',
    'active_url' => ':attribute bukan URL yang valid.',
    'after' => ':attribute harus berupa tanggal setelah :date.',
    'after_or_equal' => ':attribute harus berupa tanggal setelah atau sama dengan :date.',
    'alpha' => ':attribute hanya boleh berisi huruf.',
    'alpha_dash' => ':attribute hanya boleh berisi huruf, angka, strip, dan garis bawah.',
    'alpha_num' => ':attribute hanya boleh berisi huruf dan angka.',
    'array' => ':attribute harus berupa sebuah array.',
    'ascii' => ':attribute hanya boleh berisi karakter alfanumerik byte tunggal dan simbol.',
    'before' => ':attribute harus berupa tanggal sebelum :date.',
    'before_or_equal' => ':attribute harus berupa tanggal sebelum atau sama dengan :date.',
    'between' => [
        'array' => ':attribute harus memiliki antara :min dan :max item.',
        'file' => ':attribute harus berukuran antara :min dan :max kilobyte.',
        'numeric' => ':attribute harus bernilai antara :min dan :max.',
        'string' => ':attribute harus berisi antara :min dan :max karakter.',
    ],
    'boolean' => ':attribute harus bernilai true atau false.',
    'can' => ':attribute berisi nilai yang tidak diotorisasi.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'contains' => ':attribute kehilangan nilai yang diperlukan.',
    'current_password' => 'Kata sandi salah.',
    'date' => ':attribute bukan tanggal yang valid.',
    'date_equals' => ':attribute harus berupa tanggal yang sama dengan :date.',
    'date_format' => ':attribute tidak cocok dengan format :format.',
    'decimal' => ':attribute harus memiliki :decimal tempat desimal.',
    'declined' => ':attribute harus ditolak.',
    'declined_if' => ':attribute harus ditolak jika :other adalah :value.',
    'different' => ':attribute dan :other harus berbeda.',
    'digits' => ':attribute harus berupa :digits digit.',
    'digits_between' => ':attribute harus berukuran antara :min dan :max digit.',
    'dimensions' => ':attribute memiliki dimensi gambar yang tidak valid.',
    'distinct' => ':attribute memiliki nilai duplikat.',
    'doesnt_end_with' => ':attribute tidak boleh diakhiri dengan salah satu dari berikut: :values.',
    'doesnt_start_with' => ':attribute tidak boleh diawali dengan salah satu dari berikut: :values.',
    'email' => ':attribute harus berupa alamat email yang valid.',
    'ends_with' => ':attribute harus diakhiri dengan salah satu dari berikut: :values.',
    'enum' => ':attribute yang dipilih tidak valid.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'extensions' => ':attribute harus memiliki salah satu ekstensi berikut: :values.',
    'file' => ':attribute harus berupa sebuah berkas.',
    'filled' => ':attribute harus memiliki nilai.',
    'gt' => [
        'array' => ':attribute harus memiliki lebih dari :value item.',
        'file' => ':attribute harus berukuran lebih besar dari :value kilobyte.',
        'numeric' => ':attribute harus bernilai lebih besar dari :value.',
        'string' => ':attribute harus berisi lebih dari :value karakter.',
    ],
    'gte' => [
        'array' => ':attribute harus memiliki :value item atau lebih.',
        'file' => ':attribute harus berukuran lebih besar dari atau sama dengan :value kilobyte.',
        'numeric' => ':attribute harus bernilai lebih besar dari atau sama dengan :value.',
        'string' => ':attribute harus berisi lebih dari atau sama dengan :value karakter.',
    ],
    'hex_color' => ':attribute harus berupa kode warna heksadesimal yang valid.',
    'image' => ':attribute harus berupa gambar.',
    'in' => ':attribute yang dipilih tidak valid.',
    'in_array' => ':attribute harus ada di dalam :other.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'ip' => ':attribute harus berupa alamat IP yang valid.',
    'ipv4' => ':attribute harus berupa alamat IPv4 yang valid.',
    'ipv6' => ':attribute harus berupa alamat IPv6 yang valid.',
    'json' => ':attribute harus berupa string JSON yang valid.',
    'list' => ':attribute harus berupa daftar.',
    'lowercase' => ':attribute harus berupa huruf kecil.',
    'lt' => [
        'array' => ':attribute harus memiliki kurang dari :value item.',
        'file' => ':attribute harus berukuran kurang dari :value kilobyte.',
        'numeric' => ':attribute harus bernilai kurang dari :value.',
        'string' => ':attribute harus berisi kurang dari :value karakter.',
    ],
    'lte' => [
        'array' => ':attribute tidak boleh memiliki lebih dari :value item.',
        'file' => ':attribute harus berukuran kurang dari atau sama dengan :value kilobyte.',
        'numeric' => ':attribute harus bernilai kurang dari atau sama dengan :value.',
        'string' => ':attribute harus berisi kurang dari atau sama dengan :value karakter.',
    ],
    'mac_address' => ':attribute harus berupa alamat MAC yang valid.',
    'max' => [
        'array' => ':attribute tidak boleh memiliki lebih dari :max item.',
        'file' => ':attribute tidak boleh berukuran lebih dari :max kilobyte.',
        'numeric' => ':attribute tidak boleh bernilai lebih dari :max.',
        'string' => ':attribute tidak boleh berisi lebih dari :max karakter.',
    ],
    'max_digits' => ':attribute tidak boleh memiliki lebih dari :max digit.',
    'mimes' => ':attribute harus berupa berkas bertipe: :values.',
    'mimetypes' => ':attribute harus berupa berkas bertipe: :values.',
    'min' => [
        'array' => ':attribute harus memiliki minimal :min item.',
        'file' => ':attribute harus berukuran minimal :min kilobyte.',
        'numeric' => ':attribute harus bernilai minimal :min.',
        'string' => ':attribute harus berisi minimal :min karakter.',
    ],
    'min_digits' => ':attribute harus memiliki minimal :min digit.',
    'missing' => ':attribute harus tidak ada.',
    'missing_if' => ':attribute harus tidak ada jika :other adalah :value.',
    'missing_unless' => ':attribute harus tidak ada kecuali jika :other adalah :value.',
    'missing_with' => ':attribute harus tidak ada jika terdapat :values.',
    'missing_with_all' => ':attribute harus tidak ada jika terdapat :values.',
    'multiple_of' => ':attribute harus merupakan kelipatan dari :value.',
    'not_in' => ':attribute yang dipilih tidak valid.',
    'not_regex' => 'Format :attribute tidak valid.',
    'numeric' => ':attribute harus berupa angka.',
    'password' => [
        'letters' => ':attribute harus berisi setidaknya satu huruf.',
        'mixed' => ':attribute harus berisi setidaknya satu huruf besar dan satu huruf kecil.',
        'numbers' => ':attribute harus berisi setidaknya satu angka.',
        'symbols' => ':attribute harus berisi setidaknya satu simbol.',
        'uncompromised' => ':attribute yang diberikan telah muncul dalam kebocoran data. Silakan pilih :attribute yang berbeda.',
    ],
    'present' => ':attribute harus ada.',
    'present_if' => ':attribute harus ada jika :other adalah :value.',
    'present_unless' => ':attribute harus ada kecuali :other adalah :value.',
    'present_with' => ':attribute harus ada jika terdapat :values.',
    'present_with_all' => ':attribute harus ada jika terdapat :values.',
    'prohibited' => ':attribute dilarang diisi.',
    'prohibited_if' => ':attribute dilarang diisi jika :other adalah :value.',
    'prohibited_unless' => ':attribute dilarang diisi kecuali jika :other berada dalam :values.',
    'prohibits' => ':attribute melarang :other untuk diisi.',
    'regex' => 'Format :attribute tidak valid.',
    'required' => ':attribute wajib diisi.',
    'required_array_keys' => ':attribute harus berisi entri untuk: :values.',
    'required_if' => ':attribute wajib diisi jika :other adalah :value.',
    'required_if_accepted' => ':attribute wajib diisi jika :other diterima.',
    'required_if_declined' => ':attribute wajib diisi jika :other ditolak.',
    'required_unless' => ':attribute wajib diisi kecuali jika :other berada dalam :values.',
    'required_with' => ':attribute wajib diisi jika terdapat :values.',
    'required_with_all' => ':attribute wajib diisi jika terdapat :values.',
    'required_without' => ':attribute wajib diisi jika tidak terdapat :values.',
    'required_without_all' => ':attribute wajib diisi jika tidak terdapat satupun :values.',
    'same' => ':attribute dan :other harus sama.',
    'size' => [
        'array' => ':attribute harus berisi :size item.',
        'file' => ':attribute harus berukuran :size kilobyte.',
        'numeric' => ':attribute harus bernilai :size.',
        'string' => ':attribute harus berisi :size karakter.',
    ],
    'starts_with' => ':attribute harus diawali dengan salah satu dari berikut: :values.',
    'string' => ':attribute harus berupa string.',
    'timezone' => ':attribute harus berupa zona waktu yang valid.',
    'unique' => ':attribute sudah digunakan.',
    'uploaded' => ':attribute gagal diunggah.',
    'uppercase' => ':attribute harus berupa huruf kapital.',
    'url' => ':attribute harus berupa URL yang valid.',
    'ulid' => ':attribute harus berupa ULID yang valid.',
    'uuid' => ':attribute harus berupa UUID yang valid.',

    /*
    |--------------------------------------------------------------------------
    | Kustomisasi Baris Bahasa Validasi
    |--------------------------------------------------------------------------
    |
    | Di sini Anda dapat menentukan pesan validasi kustom untuk atribut
    | menggunakan konvensi "attribute.rule" untuk menamai baris.
    |
    */

    'custom' => [
        'router_id' => [
            'required' => 'Router wajib dipilih.',
            'exists' => 'Router yang dipilih tidak valid.',
        ],
        'ip_pool_id' => [
            'required' => 'IP Pool wajib dipilih untuk koneksi PPPoE.',
            'required_if' => 'IP Pool wajib dipilih untuk koneksi PPPoE.',
            'exists' => 'IP Pool yang dipilih tidak valid.',
        ],
        'ip_static' => [
            'required' => 'Alamat IP Statis wajib diisi untuk koneksi IP Static.',
            'required_if' => 'Alamat IP Statis wajib diisi untuk koneksi IP Static.',
            'ipv4' => 'Format Alamat IP Statis tidak valid (contoh: 192.168.1.50).',
        ],
        'pelanggan_id' => [
            'required' => 'Pelanggan wajib dipilih.',
            'exists' => 'Pelanggan yang dipilih tidak valid.',
        ],
        'paket_layanan_id' => [
            'required' => 'Paket layanan wajib dipilih.',
            'exists' => 'Paket layanan yang dipilih tidak valid.',
        ],
        'ppp_username' => [
            'required' => 'Username PPP wajib diisi.',
            'max' => 'Username PPP maksimal 64 karakter.',
            'regex' => 'Format username PPP tidak valid. Harus berupa No.Reg pelanggan diikuti underscore dan 5 digit angka (contoh: BF2308202601_00001).',
            'unique' => 'Username PPP sudah digunakan.',
        ],
        'ppp_password' => [
            'required' => 'Password PPP wajib diisi.',
            'min' => 'Password PPP minimal 4 karakter.',
            'max' => 'Password PPP maksimal 64 karakter.',
        ],
        'tanggal_mulai' => [
            'required' => 'Tanggal mulai wajib diisi.',
            'date' => 'Tanggal mulai harus berupa tanggal yang valid.',
        ],
        'tanggal_expired' => [
            'date' => 'Tanggal jatuh tempo harus berupa tanggal yang valid.',
            'after_or_equal' => 'Tanggal jatuh tempo harus setelah atau sama dengan tanggal mulai.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Atribut Validasi Kustom
    |--------------------------------------------------------------------------
    |
    | Baris bahasa berikut digunakan untuk menukar placeholder atribut kami
    | dengan sesuatu yang lebih ramah pembaca seperti "Alamat E-Mail" alih-alih "email".
    |
    */

    'attributes' => [
        'router_id' => 'Router Gateway',
        'ip_pool_id' => 'IP Pool',
        'ip_static' => 'IP Statis',
        'pelanggan_id' => 'Pelanggan',
        'paket_layanan_id' => 'Paket Layanan',
        'ppp_username' => 'Username PPP',
        'ppp_password' => 'Password PPP',
        'jenis_koneksi' => 'Jenis Koneksi',
        'tanggal_mulai' => 'Tanggal Mulai',
        'tanggal_expired' => 'Tanggal Jatuh Tempo',
        'status' => 'Status Layanan',
    ],

];
