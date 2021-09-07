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
        $districtId = District::where('name', $data['kecamatan'])->where('city_id', $city['id'])->value('id');
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
            'district_id' => $districtId,
            'district_name' => $data['kecamatan'],
            'subdistrict_id' => Subdistrict::where('name', $data['kelurahan'])->where('district_id', $districtId)->value('id'),
            'subdistrict_name' => $data['kelurahan'],
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
            case 'Kab. Indramayu':
                $city = [
                    'name' => 'INDRAMAYU',
                    'id' => 3212
                ];
                break;
            case 'Kab. Bekasi':
                $city = [
                    'name' => 'BEKASI',
                    'id' => 3216
                ];
                break;
            case 'Kab. Sukabumi':
                $city = [
                    'name' => 'SUKABUMI',
                    'id' => 3202
                ];
                break;
            case 'Kab. Bandung':
                $city = [
                    'name' => 'BANDUNG',
                    'id' => 3204
                ];
                break;
            case 'Kab. Bandung Barat':
                $city = [
                    'name' => 'BANDUNG BARAT',
                    'id' => 3217
                ];
                break;
            case 'Kab. Bogor':
                $city = [
                    'name' => 'BOGOR',
                    'id' => 3201
                ];
                break;
            case 'Kab. Sumedang':
                $city = [
                    'name' => 'SUMEDANG',
                    'id' => 3211
                ];
                break;
            case 'Kota Depok':
                $city = [
                    'name' => 'DEPOK',
                    'id' => 3276
                ];
                break;
            case 'Kota Cimahi':
                $city = [
                    'name' => 'CIMAHI',
                    'id' => 3277
                ];
                break;
            case 'Kab. Karawang':
                $city = [
                    'name' => 'KARAWANG',
                    'id' => 3215
                ];
                break;
            case 'Kab. Garut':
                $city = [
                    'name' => 'GARUT',
                    'id' => 3205
                ];
                break;
            case 'Kab. Cirebon':
                $city = [
                    'name' => 'CIREBON',
                    'id' => 3209
                ];
                break;
            case 'Kab. Ciamis':
                $city = [
                    'name' => 'CIAMIS',
                    'id' => 3207
                ];
                break;
            case 'Kab. Purwakarta':
                $city = [
                    'name' => 'PURWAKARTA',
                    'id' => 3214
                ];
                break;
            case 'Kab. Majalengka':
                $city = [
                    'name' => 'MAJALENGKA',
                    'id' => 3210
                ];
                break;
            case 'Kab. Subang':
                $city = [
                    'name' => 'SUBANG',
                    'id' => 3213
                ];
                break;
            case 'Kab. Cianjur':
                $city = [
                    'name' => 'CIANJUR',
                    'id' => 3203
                ];
                break;
            case 'Kab. Tasikmalaya':
                $city = [
                    'name' => 'TASIKMALAYA',
                    'id' => 3206
                ];
                break;
            case 'Kab. Kuningan':
                $city = [
                    'name' => 'KUNINGAN',
                    'id' => 3208
                ];
                break;
            case 'Kab. Pangandaran':
                $city = [
                    'name' => 'PANGANDARAN',
                    'id' => 3218
                ];
                break;
            default:
                $city = [
                    'id' => City::where('name', $kabkot)->value('id'),
                    'name' => $kabkot
                ];
                break;
        }

        return $city;
    }

    // public function mappingDistrict($district)
    // {
    //     switch ($district) {
    //         case 'Medansatria' : $name = 'Medan Satria'; break;
    //         case 'Gunung Jati' : $name = 'GUNUNGJATI'; break;
    //         case 'Pondokmelati' : $name = 'PONDOK MELATI'; break;
    //         case 'Kuntawaringin' : $name = 'KUTAWARINGIN'; break;
    //         case 'Kotabaru' : $name = 'KOTA BARU'; break;
    //         case 'buahbatu' : $name = 'BUAH BATU'; break;
    //         case 'Panyileukan' : $name = 'PANYILEUKAN'; break;
    //         case 'Kedungwaringin'  : $name = 'KEDUNG WARINGIN'; break;
    //         case 'Tajur Halang' : $name = 'TAJURHALANG' ; break;
    //         case 'Karangbahagia'  : $name = 'KARANG BAHAGIA'; break;
    //         case 'Kelapa Nunggal' : $name = 'KLAPANUNGGAL' ; break;
    //     }

    //     return $name;
    // }
}
