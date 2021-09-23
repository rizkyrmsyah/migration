<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\District;
use App\Models\IsomanFunnel;
use App\Models\IsomanVerifikasi;
use App\Models\Package;
use App\Models\Request;
use App\Models\Subdistrict;
use App\Models\TestLocation;
use App\Models\TestType;
use App\Models\Verification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateData extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $datas = IsomanFunnel::select(
            'isoman_funnel.*',
            'isoman_control_tracking.verif_data_status',
            'isoman_control_tracking.verif_data_notes',
            'isoman_control_tracking.verif_labtest_status',
            'isoman_control_tracking.verif_labtest_notes',
            'isoman_control_tracking.verif_resep_status',
            'isoman_control_tracking.verif_resep_notes',
            'isoman_control_tracking.verif_farmasi_status',
            'isoman_control_tracking.verif_farmasi_notes',
            'isoman_control_tracking.verif_shipping_notes',
            'isoman_control_tracking.final_status',
            'isoman_control_tracking.final_status_notes',
            'isoman_verif_shipping.shipping_status',
            'isoman_verif_shipping.nama_kurir',
            'isoman_verif_shipping.tracking_link',
            'isoman_verif_shipping.kode_tracking',
            'isoman_verif_shipping.additional_note_shipping_note_1',
            DB::raw('DATE(isoman_verif_shipping.received_date) AS received_date')
        )
            ->leftJoin('isoman_control_tracking', 'isoman_control_tracking.id_permohonan', 'isoman_funnel.id_permohonan')
            ->leftJoin('isoman_verif_shipping', 'isoman_verif_shipping.id_permohonan', 'isoman_funnel.id_permohonan')
            ->get();

        DB::beginTransaction();
        try {
            $count = 0;
            $total = count($datas);
            foreach ($datas as $data) {
                $exists = Request::where('request_number', $data['id_permohonan'])->exists();
                if (!$exists) {
                    $request = Request::create($this->mappingRequestData($data));
                    Verification::create($this->mappingVerificationData($data, $request->id));
                }
                $count++;
                echo "{$count} of {$total} data migrated\n";
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollback();
            throw $th;
        }
    }

    public function mappingRequestData($data): array
    {
        $city = $this->mappingCity($data['kabkot']);

        $district = $this->mappingDistrict($data['kecamatan'], $city['id']);
        if ($district['name'] == 'PANYILEUKAN') {
            $city['id'] = 3273;
            $city['name'] = 'KOTA BANDUNG';
            $district['id'] = 327328;
        }
        if ($district['name'] == 'WARUDOYONG') {
            $city['id'] = 3272;
            $city['name'] = 'KOTA SUKABUMI';
            $district['id'] = 327204;
        }

        $subdistrict = $this->mappingSubdistrict($data['kelurahan'], $district['id']);
        $type = 'vitamin';
        if ($data['kategori'] == '99-GJ') {
            $type = 'obat_vitamin';
        }

        if ($data['id_permohonan'] == 'REQ-0000021492') {
            $data['nama_dokter'] = "Diah";
            $data['paket_obat'] = "Paket C";
            $data['screenshot_bukti_konsultasi'] = null;
            $data['id_tiket'] = "#72747";
        }

        switch ($data['paket_obat']) {
            case 'Paket A.1': $data['paket_obat'] = 'Paket A1'; break;
            case 'Paket B.1': $data['paket_obat'] = 'Paket B1'; break;
            case 'Paket C.1': $data['paket_obat'] = 'Paket C1'; break;
            case 'Paket D.1': $data['paket_obat'] = 'Paket D1'; break;
        }

        // Kalo paket obat nya null, ambil nya ke kolom sumber permohonan soalnya si nama paketnya ada disana
        // contoh data : REQ-0000016889 REQ-0000011601
        if ($data['paket_obat'] == null) {
            if ($data['sumber_permohonan'] == '"Paket A - Vitamin"') {
                $data['paket_obat'] = 'Paket A';
            }
            if ($data['sumber_permohonan'] == 'Paket B - Obat Antivirus dan Vitamin') {
                $data['paket_obat'] = 'Paket B';
            }
        }

        // kalo paket_obat sama sumber_permohonan dari funnel nya null, ambil ke table isoman_verifikasi
        if ($data['paket_obat'] == null && $data['sumber_permohonan'] == null) {
            $jenisPaket = IsomanVerifikasi::where('id_permohonan', $data['id_permohonan'])->value('jenis_paket');
            if ($jenisPaket == '"Paket A - Vitamin"' || $jenisPaket == 'Paket A - Vitamin') {
                $data['paket_obat'] = 'Paket A';
            }
            if ($jenisPaket == 'Paket B - Obat Antivirus dan Vitamin' || $jenisPaket == 'Paket B') {
                $data['paket_obat'] = 'Paket B';
            }
        }

        return [
            'request_type' => $type,
            'is_from_migration' => 1,
            'request_number' => $data['id_permohonan'],
            'created_date' => $data['created_date'],
            'created_at' => $data['created_at'],
            'name' => $data['nama_lengkap'],
            'phone_primary' => $data['no_telepon_primary'],
            'phone_secondary' => $data['no_telepon_secondary'],
            'email' => $data['email'],
            'city_id' => $city['id'],
            'city_name' => Str::upper($city['name']),
            'district_id' => $district['id'],
            'district_name' => Str::upper($district['name']),
            'subdistrict_id' => $subdistrict['id'],
            'subdistrict_name' => Str::upper($subdistrict['name']) ,
            'address' => $data['alamat_lengkap'],
            'rt' => $data['rt'],
            'rw' => $data['rw'],
            'landmark' => $data['keterangan_alamat'],
            'nik' => $data['nik'],
            'ktp_photo' => $data['foto_ktp'],
            'birth_date' => $data['tanggal_lahir'],
            'age' => $data['umur'],
            'date_check' => $data['tanggal_pemeriksaan'],
            'date_confirmation' => $data['tanggal_konfirmasi_positif'],
            'is_reported' => $data['sudah_lapor'],
            'is_reported_tracing' => $data['sudah_tracing_kontak_erat'],
            'test_result_photo' => $data['foto_hasil_lab'],
            'consultation_ticket_id' => $data['id_tiket'],
            'doctor_name' => $data['nama_dokter'],
            'prescription_photo' => $data['screenshot_bukti_konsultasi'],
            'category' => $data['kategori'],
            'submission_id' => $data['submission_id'],
            'test_location_id' => $data['lokasi_tes_lab'] == 'LAINNYA' ? null : TestLocation::where('name', $data['lokasi_tes_lab'])->withTrashed()->value('id'),
            'test_location_name' => $data['lokasi_tes_lab'],
            'other_test_location' => $data['lokasi_tes_lab_lainnya'],
            'test_type_id' => TestType::where('name', $data['jenis_tes'])->withTrashed()->value('id'),
            'test_type_name' => $data['jenis_tes'],
            'other_test_type' => $data['jenis_tes_lainnya'],
            'package_id' => $data['paket_obat'] ? Package::where('name', $data['paket_obat'])->withTrashed()->value('id') : null,
            'package_name' => $data['paket_obat'] ? $data['paket_obat'] : null,
        ];
    }

    public function mappingVerificationData($data, $requestId): array
    {
        return [
            'request_id' => $requestId,
            'request_number' => $data['id_permohonan'],
            'verif_data_status' => $data['verif_data_status'],
            'verif_data_note' => $data['verif_data_notes'],
            'verif_labtest_status' => $data['verif_labtest_status'],
            'verif_labtest_note' => $data['verif_labtest_notes'],
            'verif_prescription_status' => $data['verif_resep_status'],
            'verif_prescription_note' => $data['verif_resep_notes'],
            'verif_packing_status' => $data['verif_farmasi_status'],
            'verif_packing_note' => $data['verif_farmasi_notes'],
            'shipping_status' => $data['shipping_status'],
            'shipping_note' => $data['verif_shipping_note'],
            'shipping_url' => $data['tracking_link'],
            'shipping_courier_name' => $data['nama_kurir'],
            'shipping_code' => $data['kode_tracking'],
            'shipping_receiver' => $data['additional_note_shipping_note_1'],
            'shipping_receive_date' => $data['received_date'],
            'final_status' => $data['final_status'],
            'final_status_note' => $data['final_status_notes'],
        ];
    }

    public function mappingCity($city)
    {
        switch ($city) {
            case 'Kab. Indramayu': $name = 'INDRAMAYU'; break;
            case 'Kab. Bekasi': $name = 'BEKASI'; break;
            case 'Kab. Sukabumi': $name = 'SUKABUMI'; break;
            case 'Kab. Bandung': $name = 'BANDUNG'; break;
            case 'Kab. Bandung Barat': $name = 'BANDUNG BARAT'; break;
            case 'Kab. Bogor': $name = 'BOGOR'; break;
            case 'Kab. Sumedang': $name = 'SUMEDANG'; break;
            case 'Kota Depok': $name = 'DEPOK'; break;
            case 'Kota Cimahi': $name = 'CIMAHI'; break;
            case 'Kab. Karawang': $name = 'KARAWANG'; break;
            case 'Kab. Garut': $name = 'GARUT'; break;
            case 'Kab. Cirebon': $name = 'CIREBON'; break;
            case 'Kab. Ciamis': $name = 'CIAMIS'; break;
            case 'Kab. Purwakarta': $name = 'PURWAKARTA'; break;
            case 'Kab. Majalengka': $name = 'MAJALENGKA'; break;
            case 'Kab. Subang': $name = 'SUBANG'; break;
            case 'Kab. Cianjur': $name = 'CIANJUR'; break;
            case 'Kab. Tasikmalaya': $name = 'TASIKMALAYA'; break;
            case 'Kab. Kuningan': $name = 'KUNINGAN'; break;
            case 'Kab. Pangandaran': $name = 'PANGANDARAN'; break;
            default: $name = $city; break;
        }

        return [
            'id' => City::where('name', $name)->value('id'),
            'name' => $name,
        ];
    }

    public function mappingDistrict($district, $cityId)
    {
        switch ($district) {
            case 'Medansatria' : $name = 'Medan Satria'; break;
            case 'Gunung Jati' : $name = 'GUNUNGJATI'; break;
            case 'Pondokmelati' : $name = 'PONDOK MELATI'; break;
            case 'Kuntawaringin' : $name = 'KUTAWARINGIN'; break;
            case 'Kotabaru' : $name = 'KOTA BARU'; break;
            case 'Buahbatu' : $name = 'BUAH BATU'; break;
            case 'Panyileukan' : $name = 'PANYILEUKAN'; break;
            case 'Kedungwaringin'  : $name = 'KEDUNG WARINGIN'; break;
            case 'Tajur Halang' : $name = 'TAJURHALANG' ; break;
            case 'Karangbahagia'  : $name = 'KARANG BAHAGIA'; break;
            case 'Kelapa Nunggal' : $name = 'KLAPANUNGGAL' ; break;
            case 'Solokan Jeruk' : $name = 'SOLOKANJERUK'; break;
            case 'Jampangkulon' : $name = 'JAMPANG KULON'; break;
            case 'Jampangtengah' : $name = 'JAMPANG TENGAH'; break;
            case 'Gununghalu' : $name = 'GUNUNG HALU'; break;
            case 'Purbasari' : $name = 'PURWASARI'; break;
            case 'Cikalongwetan' : $name = 'CIKALONG WETAN'; break;
            case 'Ranca Bungur' : $name = 'RANCABUNGUR'; break;
            case 'Gunungpuyuh' : $name = 'GUNUNG PUYUH'; break;
            case 'Warungdoyonga' : $name = 'WARUDOYONG'; break;
            case 'Susukanlebak' : $name = 'SUSUKAN LEBAK'; break;
            case 'Sindang Agung' : $name = 'SINDANGAGUNG'; break;
            case 'Parungkuda' : $name = 'PARUNG KUDA'; break;
            case 'Mustikajaya' : $name = 'MUSTIKA JAYA'; break;
            case 'Bogor Selatan' : $name = 'KOTA BOGOR SELATAN'; break;
            case 'Bogor Barat' : $name = 'KOTA BOGOR BARAT'; break;
            case 'Bogor Utara' : $name = 'KOTA BOGOR UTARA'; break;
            case 'Bogor Timur' : $name = 'KOTA BOGOR TIMUR'; break;
            case 'Lemah Wungkuk' : $name = 'LEMAHWUNGKUK'; break;
            case 'Bogor Tengah' : $name = 'KOTA BOGOR TENGAH'; break;
            case 'Warungkiara' : $name = 'WARUNG KIARA'; break;
            case 'Kedokanbunder' : $name = 'KEDOKANBUNDER'; break;
            case 'Gegerbitung' : $name = 'GEGER BITUNG'; break;
            case 'Kalapanunggal' : $name = 'KALAPA NUNGGAL'; break;
            case 'Banjaranyar' : $name = 'BANJARSARI'; break;
            case 'Talagasari' : $name = 'TELAGASARI'; break;
            case 'Parungpoteng' : $name = 'PARUNGPONTENG'; break;
            case 'Ujungjaya' : $name = 'UJUNG JAYA'; break;
            case 'Tegalwaru' :
                $name = 'TEGAL WARU';
                if ($cityId == 3215) {
                    $name = 'TEGALWARU';
                }
                break;
            case 'Blubur Limbangan' : $name = 'BL. LIMBANGAN'; break;
            case 'Parakansalak' : $name = 'PARAKAN SALAK'; break;
            case 'Cikakap' : $name = 'CIKAKAK'; break;
            case 'Pondok Salam' : $name = 'PONDOKSALAM'; break;
            default : $name = $district;
        }

        return [
            'id' => District::where('name', Str::upper($name))->where('city_id', $cityId)->value('id'),
            'name' => $name
        ];
    }

    public function mappingSubdistrict($subdistrict, $districtId)
    {
        switch ($subdistrict) {
            case 'Mekar jaya': $name = 'MEKARJAYA'; break;
            case 'Babakanpeuteuy': $name = 'BABAKAN PEUTEUY'; break;
            case 'Babakansari':
                $name = 'BABAKANSARI';
                if ($districtId == 321002) {
                    $name = 'BABAKAN SARI';
                }
                break;
            case 'Babakan sari':
                $name = 'BABAKAN SARI'; // kircon
                if($districtId == 321404) { // Purwakarta
                    $name = 'BABAKANSARI';
                }
                break;
            case 'BABAKAN SARI': $name = 'BABAKANSARI'; break; // sukaluyu cianjur
            case 'Bakti jaya': $name = 'BAKTIJAYA'; break;
            case 'Balungbangjaya' : $name = 'BALUMBANG JAYA'; break;
            case 'Bandorasa kulon' : $name = 'BANDORASAKULON'; break;
            case 'Bandorasa wetan' : $name = 'BANDORASAWETAN'; break;
            case 'Banjaran' : $name = 'BANJARAN'; break;
            case 'Banjar sari' : $name = 'BANJARSARI'; break;
            case 'Banjar waru' : $name = 'BANJARWARU'; break;
            case 'Balong gede' : $name = 'BALONGGEDE'; break;
            case 'Bantar jati' : $name = 'BANTARJATI'; break;
            case 'Bantar kuning' : $name = 'BANTARKUNING'; break;
            case 'Bantar sari' : $name = 'BANTARSARI'; break;
            case 'Batembat' : $name = 'BATTEMBAT'; break;
            case 'Batu layang' : $name = 'BATULAYANG'; break;
            case 'Bitung sari' : $name = 'BITUNGSARI'; break;
            case 'Bojong gede' : $name = 'BOJONGGEDE'; break;
            case 'Bojong indah' : $name = 'BOJONGINDAH'; break;
            case 'Bojong kokosan' : $name = 'BOJONGKOKOSAN'; break;
            case 'Bojongcideres' : $name = 'BOJONG CIDERES'; break;
            case 'Bojongkidul' : $name = 'BOJONG KIDUL'; break;
            case 'Bojongsari lama' : $name = 'BOJONGSARI'; break;
            case 'Bojong baru' : $name = 'BOJONGBARU'; break;
            case 'Cadas ngampar' : $name = 'CADASNGAMPAR'; break;
            case 'Campakasari' : $name = 'CAMPAKASARI'; break;
            case 'Cempakamekar' : $name = 'CAMPAKAMEKAR'; break;
            case 'Cibatok 1' : $name = 'CIBATOK I'; break;
            case 'Cibunigeulis' : $name = 'CIBUNIGEULIS'; break;
            case 'Cikumpay' : $name = 'CIKUMPAY'; break;
            case 'Cimanggu 1' : $name = 'CIMANGGU I'; break;
            case 'Cimanggu 2' : $name = 'CIMANGGU II'; break;
            case 'Cintalaksana' : $name = 'CINTALAKSANA'; break;
            case 'Cintawargi' : $name = 'CINTAWARGI'; break;
            case 'CIRIMEKAR' : $name = 'CIRIMEKAR'; break;
            case 'Ciaruten ilir' : $name = 'CIARUTEUN ILIR'; break;
            case 'Cibogo girang' : $name = 'CIBOGOGIRANG'; break;
            case 'Cileduk' : $name = 'CILEDUG'; break;
            case 'Cimekar' :
                $name = 'CIRIMEKAR';
                if ($districtId == 320405) {
                    $name = 'CIMEKAR';
                }
                break;
            case 'Cijaura' : $name = 'CIJAWURA'; break;
            case 'Gunung menyan' : $name = 'GUNUNGMENYAN'; break;
            case 'Gunung sari' : $name = 'GUNUNGSARI'; break;
            case 'Gunung sindur' : $name = 'GUNUNGSINDUR'; break;
            case 'Gunungcupu' : $name = 'GUNUNG CUPU'; break;
            case 'Gununggede' : $name = 'GUNUNG GEDE'; break;
            case 'Gunungtandala' : $name = 'GUNUNG TANDALA'; break;
            case 'Gempol sari' : $name = 'GEMPOLSARI'; break;
            case 'Gempolkolot' : $name = 'GEMPOL KOLOT'; break;
            case 'Gedong panjang' : $name = 'GEDONGPANJANG'; break;
            case 'Gajahmekar' : $name = 'GAJAH MEKAR'; break;
            case 'Harapan Jaya' :
                $name = 'HARAPAN JAYA';
                if ($districtId == 320101) {
                    $name = 'HARAPANJAYA';
                }
                break;
            case 'Huripjaya' : $name = 'Hurip Jaya'; break;
            case 'Jabon mekar' : $name = 'JABONMEKAR'; break;
            case 'Jampang kulon' : $name = 'JAMPANGKULON'; break;
            case 'Jati sari' : $name = 'JATISARI'; break;
            case 'Jatitengah' : $name = 'JATI TENGAH'; break;
            case 'Kadumangu' : $name = 'KADUMANGGU'; break;
            case 'Kaplonganlor' : $name = 'KAPLONGAN LOR'; break;
            case 'Karang anyar' : $name = 'KARANGANYAR'; break;
            case 'Karang tengah' : $name = 'KARANGTENGAH'; break;
            case 'Karangtengah' :
                $name = 'KARANG TENGAH';
                if ($districtId == in_array($districtId, [320319, 320906])) {
                    $name = 'KARANGTENGAH';
                }

                break;
            case 'KARANG TENGAH' : $name = 'KARANGTENGAH'; break;
            case 'Kebon gedang' : $name = 'KEBONGEDANG'; break;
            case 'Kebon lega' : $name = 'KEBONLEGA'; break;
            case 'Kebon waru' : $name = 'KEBONWARU'; break;
            case 'Kebun jayanti' : $name = 'KEBON JAYANTI'; break;
            case 'Kedokanbunder' : $name = 'KEDOKAN BUNDER'; break;
            case 'Kedungjaya' :
                $name = 'KEDUNG JAYA';
                if ($districtId == in_array($districtId, [320920, 327106])) {
                    $name = 'KEDUNGJAYA';
                }
                break;
            case 'Kedung waringin' : $name = 'KEDUNGWARINGIN'; break;
            case 'Kedungpengawas' : $name = 'KEDUNG PENGAWAS'; break;
            case 'Kelapa nunggal' : $name = 'KLAPANUNGGAL'; break;
            case 'Kertamukti' : $name = 'KERTAMUKTI'; break;
            case 'Kotawetan' : $name = 'KOTA WETAN'; break;
            case 'Kotakulon' :
                $name = 'KOTA KULON';
                if ($districtId == 321117) {
                    $name = 'KOTAKULON';
                }
                break;
            case 'Lebak gede' : $name = 'LEBAKGEDE'; break;
            case 'Lemahabang' :
                $name = 'LEMAH ABANG';
                if ($districtId == 320907) {
                    $name = 'LEMAHABANG';
                }
                break;
            case 'Lemahmekar' : $name = 'LEMAH MEKAR'; break;
            case 'Lembursitu' : $name = 'LEMBURSITU'; break;
            case 'Leuweung kolot' : $name = 'LEUWEUNGKOLOT'; break;
            case 'Leuwinaggung' : $name = 'LEUWINANGGUNG'; break;
            case 'Lewobaru' : $name = 'LEWO BARU'; break;
            case 'Limbanganbarat' : $name = 'LIMBANGAN BARAT'; break;
            case 'Limus nunggal' : $name = 'LIMUSNUNGGAL'; break;
            case 'Mangkurayat' : $name = 'MANGKURAKYAT'; break;
            case 'Marguhurip' : $name = 'MARGAHURIP'; break;
            case 'Mekar rahayu' : $name = 'MEKARRAHAYU'; break;
            case 'Mekar wangi' : $name = 'MEKARWANGI'; break;
            case 'Muara sanding' : $name = 'Muarasanding'; break;
            case 'Nageri kaler' : $name = 'NAGRIKALER'; break;
            case 'Nageri kidul' : $name = 'NAGRIKIDUL'; break;
            case 'Nageri tengah' : $name = 'NAGRITENGAH'; break;
            case 'Pabeanudik' : $name = 'PABEAN UDIK'; break;
            case 'Palawad' : $name = 'PLAWAD'; break;
            case 'Pamager sari' : $name = 'PAMEGARSARI' ; break;
            case 'Pangkalanjati' : $name = 'PANGKALAN JATI'; break;
            case 'Pangkalanjati baru' : $name = 'PanGKALAN JATI BARU'; break;
            case 'Pantaimakmur' : $name = 'PANTAI MAKMUR'; break;
            case 'Parakan jaya' : $name = 'PARAKANJAYA'; break;
            case 'Pasir angin' : $name = 'PASIRANGIN'; break;
            case 'Pasir eurih' : $name = 'PASIREURIH'; break;
            case 'Pasir jaya' : $name = 'PASIRJAYA'; break;
            case 'Pasiranjung' : $name = 'PASIRNANJUNG'; break;
            case 'Pasirsari' : $name = 'PASIR SARI'; break;
            case 'PASI RANJUNG' : $name = 'PASIRNANJUNG'; break;
            case 'Pasir biru' : $name = 'PASIRBIRU'; break;
            case 'Pasir wangi' : $name = 'PASIRWANGI'; break;
            case 'Pasirjati' : $name = 'PASIR JATI'; break;
            case 'Pasirkaliki' :
                $name = 'PASIR KALIKI';
                if ($districtId == in_array($districtId, [327703, 321518])) {
                    $name = 'PASIRKALIKI';
                }
                break;
            case 'Patrol' : $name = 'P A T R O L'; break;
            case 'Patrollor' : $name = 'PATROL LOR'; break;
            case 'Pinangraja' : $name = 'PINANG RAJA'; break;
            case 'Plered' : $name = 'P L E R E D'; break;
            case 'Pondok kaso landeuh' : $name = 'PONDOKKASO LANDEUH'; break;
            case 'Pondok kaso tonggoh' : $name = 'PONDOKKASO Tonggoh'; break;
            case 'Pusakarakyat' : $name = 'PUSAKA RAKYAT'; break;
            case 'Rajamandala kulon' : $name = 'RAJAMANDALAKULON'; break;
            case 'Ranca bungur' : $name = 'RANCABUNGUR'; break;
            case 'Ranca senggang' : $name = 'RANCASENGGANG'; break;
            case 'Ratujaya' : $name = 'RATU JAYA'; break;
            case 'Rawa panjang' : $name = 'RAWAPANJANG'; break;
            case 'Sadang serang' : $name = 'SADANGSERANG'; break;
            case 'Samudrajaya' : $name = 'SAMUDRA JAYA'; break;
            case 'Sasak panjang' : $name = 'SASAKPANJANG'; break;
            case 'Satriajaya' : $name = 'SATRIA JAYA'; break;
            case 'Satriamekar' : $name = 'SATRIA MEKAR'; break;
            case 'Sawangan lama' : $name = 'SAWANGAN'; break;
            case 'Segaramakmur' : $name = 'SEGARA MAKMUR'; break;
            case 'Sepanjangjaya' : $name = 'SEPANJANG JAYA'; break;
            case 'Setiamulya' :
                $name = 'SETIA MULYA';
                if ($districtId == 327807) {
                    $name = 'SETIAMULYA';
                }
                break;
            case 'Setu sari' : $name = 'SITUSARI'; break;
            case 'Sigaracipta' : $name = 'SAGARACIPTA'; break;
            case 'Sinarsari' : $name = 'SIRNASARI'; break;
            case 'Sudajaya hilir' : $name = 'SUDAJAYAHILIR'; break;
            case 'Suka asih' : $name = 'SUKAASIH'; break;
            case 'Suka menak' : $name = 'SUKAMENAK'; break;
            case 'Sukamajukaler' : $name = 'SUKAMAJU KALER'; break;
            case 'Sukamantri' :
                $name = 'SUKAMENTRI';
                if ($districtId == in_array($districtId, [321120, 320131, 320229, 320636, 320435])) {
                    $name = 'SUKAMANTRI';
                }

                break;
            case 'Sukaresmi' :
                $name = 'SUKA RESMI';
                if ($districtId == in_array($districtId, [327106, 320229, 320131])) {
                    $name = 'SUKARESMI';
                }
                break;
            case 'Sukasejati' : $name = 'SUKA SEJATI'; break;
            case 'Sinar sari' : $name = 'SINARSARI'; break;
            case 'Sukra' : $name = 'S U K R A'; break;
            case 'Sulaeman' : $name = 'SULAIMAN'; break;
            case 'Sumur batu' :
                $name = 'SUMURBATU';
                if ($districtId == 327507) {
                    $name = 'SUMUR BATU';
                }
                break;
            case 'Sumurbandung' : $name = 'SUMUR BANDUNG'; break;
            case 'Tajur halang' :
                $name = 'TAJURHALANG';
                if ($districtId == 320128) {
                    $name = 'TAJUR HALANG';
                }
                break;
            case 'Taman rahayu' : $name = 'TAMANRAHAYU'; break;
            case 'Taman sari' : $name = 'TAMANSARI'; break;
            case 'Tanjungmekar' : $name = 'TANJUNG MEKAR'; break;
            case 'Tegal panjang' : $name = 'TEGALPANJANG'; break;
            case 'Tegal waru' : $name = 'TEGALWARU'; break;
            case 'Telaga asih' : $name = 'TELAGAASIH'; break;
            case 'Tugu jaya' : $name = 'TUGUJAYA'; break;
            case 'Waringin jaya' : $name = 'WARINGINJAYA'; break;
            case 'Wates jaya' : $name = 'WATESJAYA'; break;

            default: $name = $subdistrict; break;
        }

        return [
            'id' => Subdistrict::where('name', Str::upper($name))->where('district_id', $districtId)->value('id'),
            'name' => $name,
        ];
    }
}
