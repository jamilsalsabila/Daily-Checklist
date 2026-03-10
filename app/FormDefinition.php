<?php

declare(strict_types=1);

final class FormDefinition
{
    public static function openingSections(): array
    {
        return [
            [
                'title' => 'Pizza Area Checklist',
                'key' => 'pizza_area',
                'items' => [
                    'Meja Atas - lap dan keringkan, pastikan tidak ada bekas air mengering.',
                    'Meja Bawah - lap dan keringkan, rapihkan barang di atas meja, bersihkan wastafel dan pastikan kembali kering dan bersih.',
                    'Kolong Meja - sapu bersih termasuk kolong tungku pizza, pel menggunakan wipol dan rapikan barang-barang.',
                    'Tanaman dan Pot - siram tanaman secukupnya, lap pot, bersihkan daun kering dan rapikan batu-batu.',
                ],
            ],
            [
                'title' => 'Patio Checklist (1/2)',
                'key' => 'patio_1',
                'items' => [
                    'Area Panel Listrik - pastikan lantai bersih, bersihkan dan rapikan cleaning tools.',
                    'Railing Besi Tanaman - rapihkan tanpa memotong daun yang merambat ke area tamu.',
                    'Besi Plafon dan Papan Kapur - cek kondisi dan bersihkan, laporkan jika ada kerusakan.',
                    'Lampu Tempel Dinding & Figura - cek kondisi dan bersihkan menggunakan kemoceng.',
                    'Lis Beton Sudut & Baby Chair - cek kondisi dan bersihkan menggunakan kemoceng.',
                    'Tiang Kuning - cek kondisi dan bersihkan menggunakan kemoceng dan lap bila diperlukan.',
                    'Area Pohon - cek kondisi dan bersihkan daun kering.',
                    'Tempat Sampah - pastikan tempat sampah bersih sebelum memasang plastik sampah.',
                ],
            ],
            [
                'title' => 'Patio Checklist (2/2)',
                'key' => 'patio_2',
                'items' => [
                    'Lemari Menu Depan Kasir - pastikan kebersihan lemari, lap bagian atas dan kaca serta rapikan barang di dalamnya.',
                    'Lis Beton Depan Kitchen - cek kebersihan dan bersihkan dengan kemoceng kemudian dilap basah.',
                    'Lantai Paving dan Selasar - sapu bersih tidak ada sampah kecil berserakan dan bersihkan puntung rokok atau lumut di sela paving.',
                    'Station Waiter - cek kebersihan area serta ketersediaan kertas order, papan order dan pulpen.',
                    'Area Serving Makanan - cek kebersihan area dan ketersediaan perlengkapan (saos, cup saos, kresek, stiker logo, wipol).',
                    'Meja dan Kursi - cek kebersihan serta luruskan posisi meja dan kursi.',
                    'Lemari Culteries - cek kebersihan dan ketersediaan sendok, garpu, pisau, garpu steak, garpu light meal, tissue lipat dan baki makanan.',
                    'Table Tools (Jar & Menu Scan) - lap jar dengan tissue bersih, bersihkan kotak tissue, rapikan table number dan pastikan posisi sesuai SOP.',
                ],
            ],
            [
                'title' => 'Indoor Area Checklist (1/2)',
                'key' => 'indoor_1',
                'items' => [
                    'Jendela - buka jendela table C dan D, jendela table B tetap tertutup. Cek kondisi dan kebersihan.',
                    'Pintu Masuk - cek kondisi dan kebersihan pintu.',
                    'Plafon dan Lampu Gantung - cek kondisi dan kebersihan plafon terutama sudut.',
                    'Figura Dinding dan Lampu - cek kondisi dan bersihkan menggunakan kemoceng.',
                    'Rak Sudut, Hiasan, Candle - cek kondisi dan kebersihan, cek nyala candle.',
                    'Kepala Gajah dan Meja - cek kondisi dan bersihkan menggunakan kemoceng dan lap bila diperlukan.',
                    'Railing Besi - cek kondisi dan bersihkan menggunakan kemoceng setiap sudutnya.',
                    'Lantai - pastikan selalu dalam keadaan bersih.',
                ],
            ],
            [
                'title' => 'Indoor Area Checklist (2/2)',
                'key' => 'indoor_2',
                'items' => [
                    'Music - pastikan music telah menyala.',
                    'Lampu - pastikan lampu indoor pagi telah menyala (lampu dinding figura, dinding bar, dan gajah).',
                    'Order Bill, Pulpen, Thermometer, Bell - pastikan kertas tersedia dan rapi, pulpen siap pakai dan bersih, thermometer dan bell berfungsi.',
                    'Meja, Kursi, Table Tools - pastikan meja kursi telah lurus, bersih, dan table tools terpasang rapi.',
                    'Koordinasi - koordinasi kesiapan area lain untuk open door (last check HT).',
                    'Pintu Masuk - buka pintu, ganjal dengan baik dan pasang keset dengan rapi dan lurus.',
                ],
            ],
            [
                'title' => 'Toilet Area Checklist',
                'key' => 'toilet',
                'items' => [
                    'AC dan Lampu - nyalakan AC dan lampu dan pastikan berfungsi dengan baik. AC dinyalakan pada suhu 19 C.',
                    'Pengharum Ruangan - pastikan pengharum ruangan berfungsi dengan baik dan bersihkan permukaannya.',
                    'Kaca dan Wastafel - lap menggunakan tissue tanpa bekas air, bersihkan wastafel dan pastikan tidak ada sumbatan.',
                    'Dinding Kubikal - pastikan dinding bersih, tidak bau rokok atau berdebu dan tidak ada lubang stiker terbuka.',
                    'Closet dan Urinior - pastikan bersih (tidak ada kotoran di sandaran dinding, tutup closet ataupun lubang closet).',
                    'Tempat Sampah - bersihkan sebelum memasang plastik dan pastikan bersih luar dan dalam.',
                    'Lantai dan Tanaman - pastikan lantai bersih dan kering serta tanaman telah disiram.',
                    'Tissue tersedia.',
                ],
            ],
            [
                'title' => 'Tangga & Janitor Area Checklist',
                'key' => 'janitor',
                'items' => [
                    'Tangga - sapu dan pel area tangga, pastikan bersih.',
                    'Janitor - sapu bersih, lantai harus kering, rapikan barang di janitor dan pastikan peralatan kebersihan digantung dan bersih.',
                    'Pintu Janitor - pastikan dalam keadaan tertutup dan bersih.',
                    'Lampu Janitor - matikan lampu bila tidak digunakan.',
                    'Lemari Depan Toilet - bersihkan permukaan lemari dan kaca, cek ketersediaan tissue dan plastik sampah serta bersihkan area kolong.',
                ],
            ],
            [
                'title' => 'Mushola Lantai 2',
                'key' => 'mushola',
                'items' => [
                    'Mukena bersih.',
                    'Sajadah bersih.',
                    'Al Quran tersedia.',
                    'Tempat wudhu bersih.',
                    'Keran air berfungsi.',
                ],
            ],
            [
                'title' => 'Outdoor Area & Room (1/2)',
                'key' => 'outdoor_1',
                'items' => [
                    'Meja Garden - cek kebersihan dan atur posisi meja lurus serta dalam kondisi baik.',
                    'Table Tools (Jar & Menu Scan) - bersihkan kotak tissue dan lap jar serta posisikan sesuai SOP.',
                    'Lampu Tempel, Kepala dan Tembok Rusa - bersihkan lampu tempel dan cek kondisi kepala rusa serta kebersihan lantai.',
                    'Selasar Outdoor - pastikan kebersihan selasar depan tenant dari area barber sampai pizza.',
                    'Lantai Paving Meja Garden - sapu bersih tidak ada sampah kecil dan bersihkan puntung rokok atau lumut di sela paving.',
                    'Taman Samping Barber - cek kebersihan taman dan bersihkan sampah seperti tissue atau puntung rokok.',
                ],
            ],
            [
                'title' => 'Outdoor Area & Room (2/2)',
                'key' => 'outdoor_2',
                'items' => [
                    'Meja Kursi Room - cek kebersihan dan atur posisi meja kursi tetap lurus.',
                    'Dinding dan Lantai - lap area bukaan jendela room dan bersihkan ornamen di dalam room.',
                    'Taman Batu dan Taman Tengah - cek kebersihan taman dari tissue, puntung rokok dan sampah lainnya.',
                    'Kolam - bersihkan kolam dari daun atau sampah kecil menggunakan saringan.',
                    'Pohon Kolam - bersihkan daun kering di area bawah pohon.',
                ],
            ],
            [
                'title' => 'Service Equipment',
                'key' => 'service_equipment',
                'items' => [
                    'Tray bersih.',
                    'Gelas siap.',
                    'Cutlery siap.',
                ],
            ],
        ];
    }

    public static function teamControlItems(): array
    {
        return [
            'crew_hadir_lengkap' => 'Crew hadir lengkap',
            'grooming_rapi' => 'Grooming crew rapi',
            'briefing_dilakukan' => 'Briefing dilakukan',
        ];
    }

    public static function serviceControlItems(): array
    {
        return [
            'sapa_10_detik' => 'Tamu disapa <= 10 detik',
            'pesanan_benar' => 'Pesanan dicatat dengan benar',
            'tidak_salah_meja' => 'Pesanan tidak salah meja',
            'minuman_cepat' => 'Minuman keluar cepat',
            'meja_kotor_dibersihkan' => 'Meja kotor segera dibersihkan',
        ];
    }

    public static function floorAwarenessItems(): array
    {
        return [
            'aktif_kontrol_floor' => 'Floor captain aktif kontrol floor',
            'server_bantu_area' => 'Server membantu area lain saat sibuk',
            'area_tetap_rapi' => 'Area tetap rapi saat ramai',
        ];
    }

    public static function closingControlItems(): array
    {
        return [
            'meja_dibersihkan' => 'Meja dibersihkan',
            'kursi_dirapikan' => 'Kursi dirapikan',
            'lantai_disapu_dipel' => 'Lantai disapu dan dipel',
            'toilet_dibersihkan' => 'Toilet dibersihkan',
            'mushola_bersih' => 'Mushola bersih',
            'ac_dimatikan' => 'AC dimatikan',
            'area_aman' => 'Area aman',
        ];
    }
}
