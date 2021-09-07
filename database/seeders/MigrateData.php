<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\District;
use App\Models\IsomanFunnel;
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
        return [
            'is_from_migration' => 1,
            'request_number' => $data['id_permohonan'],
            'created_date' => $data['created_date'],
            'created_at' => $data['created_at'],
            'name' => $data['nama_lengkap'],
            'phone_primary' => $data['no_telepon_primary'],
            'phone_secondary' => $data['no_telepon_secondary'],
            'email' => $data['email'],
            'city_name' => $data['kabkot'],
            'district_name' => $data['kecamatan'],
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
            'city_id' => City::where('name', $data['kabkot'])->value('id'),
            'district_id' => District::where('name', $data['kecamatan'])->value('id'),
            'subdistrict_id' => Subdistrict::where('name', $data['kelurahan'])->value('id'),
            'test_location_name' => $data['lokasi_tes_lab'],
            'test_location_id' => $data['lokasi_tes_lab'] == 'LAINNYA' ? null : TestLocation::where('name', $data['lokasi_tes_lab'])->value('id'),
            'other_test_location' => $data['lokasi_tes_lab_lainnya'],
            'test_type_id' => TestType::where('name', $data['jenis_tes'])->value('id'),
            'test_type_name' => $data['jenis_tes'],
            'other_test_type' => $data['jenis_tes_lainnya'],
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
}

// protected $fillable = [
//     'request_type',
//     'package_id',
// ];



// data
// tracking API
