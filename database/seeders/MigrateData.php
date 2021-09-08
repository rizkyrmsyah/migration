<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\District;
use App\Models\IsomanFunnel;
use App\Models\Package;
use App\Models\Request;
use App\Models\Subdistrict;
use App\Models\TestLocation;
use App\Models\TestType;
use App\Models\Verification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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
            'isoman_control_tracking.verif_shipping_status',
            'isoman_control_tracking.verif_shipping_notes',
            'isoman_control_tracking.final_status',
            'isoman_control_tracking.final_status_notes',
            'isoman_verif_shipping.nama_kurir',
            'isoman_verif_shipping.tracking_link',
            'isoman_verif_shipping.kode_tracking',
            'isoman_verif_shipping.additional_note_shipping_note_1',
            DB::raw('DATE(isoman_verif_shipping.received_date) AS received_date')
        )
            ->leftJoin('isoman_control_tracking', 'isoman_control_tracking.id_permohonan', 'isoman_funnel.id_permohonan')
            ->leftJoin('isoman_verif_shipping', 'isoman_verif_shipping.id_permohonan', 'isoman_funnel.id_permohonan')
            // ->where('kelurahan', 'Mekar jaya')
            ->get();

        DB::beginTransaction();
        try {
            foreach ($datas as $data) {
                $exists = Request::where('request_number', $data['id_permohonan'])->exists();
                if (!$exists) {
                    $request = Request::create($this->mappingRequestData($data));
                    Verification::create($this->mappingVerificationData($data, $request->id));
                }
            }
            DB::commit();
        } catch (\Throwable$th) {
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

        return [
            'request_type' => $data['id_tiket'] ? 'obat_vitamin' : 'vitamin',
            'is_from_migration' => 1,
            'request_number' => $data['id_permohonan'],
            'created_date' => $data['created_date'],
            'created_at' => $data['created_at'],
            'name' => $data['nama_lengkap'],
            'phone_primary' => $data['no_telepon_primary'],
            'phone_secondary' => $data['no_telepon_secondary'],
            'email' => $data['email'],
            'city_id' => $city['id'],
            'city_name' => $city['name'],
            'district_id' => $district['id'],
            'district_name' => $district['name'],
            'subdistrict_id' => $subdistrict['id'],
            'subdistrict_name' => $subdistrict['name'],
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
            'test_location_id' => $data['lokasi_tes_lab'] == 'LAINNYA' ? null : TestLocation::where('name', $data['lokasi_tes_lab'])->value('id'),
            'test_location_name' => $data['lokasi_tes_lab'],
            'other_test_location' => $data['lokasi_tes_lab_lainnya'],
            'test_type_id' => TestType::where('name', $data['jenis_tes'])->value('id'),
            'test_type_name' => $data['jenis_tes'],
            'other_test_type' => $data['jenis_tes_lainnya'],
            'package_id' => $data['paket_obat'] ? Package::where('name', $data['paket_obat'])->value('id') : 1,
            'package_name' => $data['paket_obat'] ? $data['paket_obat'] : "Paket A",
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
            'shipping_status' => $data['verif_shipping_status'],
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

    public function mappingCity($kabkot)
    {
        switch ($kabkot) {
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
            default: $name = $kabkot; break;
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
            case 'Kedokanbunder' : $name = 'KEDOKAN BUNDER'; break;
            case 'Gegerbitung' : $name = 'GEGER BITUNG'; break;
            case 'Kalapanunggal' : $name = 'KALAPA NUNGGAL'; break;
            case 'Banjaranyar' : $name = 'BANJARSARI'; break;
            case 'Talagasari' : $name = 'TELAGASARI'; break;
            case 'Parungpoteng' : $name = 'PARUNGPONTENG'; break;
            case 'Ujungjaya' : $name = 'UJUNG JAYA'; break;
            case 'Tegalwaru' : $name = 'TEGAL WARU'; break;
            case 'Blubur Limbangan' : $name = 'BL. LIMBANGAN'; break;
            case 'Parakansalak' : $name = 'PARAKAN SALAK'; break;
            case 'Cikakap' : $name = 'CIKAKAK'; break;
            case 'Pondok Salam' : $name = 'PONDOKSALAM'; break;
            case 'LEMBURSITU' : $name = 'LEMBURSITU'; break;
            default : $name = $district;
        }

        return [
            'id' => District::where('name', $name)->where('city_id', $cityId)->value('id'),
            'name' => $name
        ];
    }

    public function mappingSubdistrict($subdistrict, $districtId)
    {
        switch ($subdistrict) {
            case 'Mekar jaya': $name = 'MEKARJAYA'; break;
            case 'Babakan sari': $name = 'BABAKANSARI'; break;
            case 'Babakanpeuteuy': $name = 'BABAKAN PEUTEUY'; break;
            case 'Babakansari': $name = 'BABAKAN SARI'; break;
            case 'Bakti jaya': $name = 'BAKTIJAYA'; break;
            case 'Balungbang jaya' : $name = 'BALUMBANG JAYA'; break;
            case 'Bandorasa kulon' : $name = 'BANDORASAKULON'; break;
            case 'Bandorasa wetan' : $name = 'BANDORASAWETAN'; break;
            case 'Banjar sari' : $name = 'BANJARSARI'; break;
            case 'Banjar waru' : $name = 'BANJARWARU'; break;
            default: $name = $subdistrict; break;
        }

        return [
            'id' => Subdistrict::where('name', $name)->where('district_id', $districtId)->value('id'),
            'name' => $name,
        ];
    }
}
