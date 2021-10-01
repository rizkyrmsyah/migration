<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\District;
use App\Models\IsomanFunnel;
use App\Models\IsomanVerifikasi;
use App\Models\IsomanVerifResep;
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
        $city = $this->mappingCity(Str::upper($data['kabkot']));

        $district = $this->mappingDistrict(Str::upper($data['kecamatan']), $city['id']);
        if ($district['name'] == 'PANYILEUKAN') {
            $city['id'] = '32.73';
            $city['name'] = 'KOTA BANDUNG';
            $district['id'] = '32.73.28';
        }
        if ($district['name'] == 'WARUDOYONG') {
            $city['id'] = '32.72';
            $city['name'] = 'KOTA SUKABUMI';
            $district['id'] = '32.72.04';
        }

        $subdistrict = $this->mappingSubdistrict(Str::upper($data['kelurahan']), $district['id']);

        // dd($subdistrict);
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

        // kalo paket_obat sama sumber_permohonan dari funnel nya null, coba cari ke table isoman_verifikasi
        if ($data['paket_obat'] == null && $data['sumber_permohonan'] == null) {
            $jenisPaket = IsomanVerifikasi::where('id_permohonan', $data['id_permohonan'])->value('jenis_paket');
            if ($jenisPaket == '"Paket A - Vitamin"' || $jenisPaket == 'Paket A - Vitamin') {
                $data['paket_obat'] = 'Paket A';
            }
            if ($jenisPaket == 'Paket B - Obat Antivirus dan Vitamin' || $jenisPaket == 'Paket B') {
                $data['paket_obat'] = 'Paket B';
            }

            // kalo nama paket nya "Resep Dokter" coba cari nama paket real nya di table isoman_verif_resep
            if ($jenisPaket == 'Resep Dokter') {
                $verifResep = IsomanVerifResep::where('id_permohonan', $data['id_permohonan'])->value('jenis_paket');
                if ($verifResep == '"Paket A - Vitamin"' || $verifResep == 'Paket A - Vitamin Tanpa Konsultasi Dokter' || $verifResep == 'Paket A - Vitamin dengan Konsultasi Dokter') {
                    $data['paket_obat'] = 'Paket A';
                }
                if ($verifResep == 'Paket B - Vitamin & Obat dengan konsultasi dokter (Gejala Ringan-Non Komorbid)') {
                    $data['paket_obat'] = 'Paket B';
                }
                if ($verifResep == 'Paket C - Vitamin & Obat dengan konsultasi dokter (Gejala Ringan-Komorbid)') {
                    $data['paket_obat'] = 'Paket C';
                }
                if ($verifResep == 'Paket D - Obat dengan konsultasi dokter (Oseltamivir)') {
                    $data['paket_obat'] = 'Paket D';
                }
                if ($verifResep == 'Paket E - Obat dengan konsultasi dokter (Favirapir)') {
                    $data['paket_obat'] = 'Paket E';
                }
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
        return [
            'id' => City::where('name', $city)->value('id'),
            'name' => $city,
        ];
    }

    public function mappingDistrict($district, $cityId)
    {
        switch ($district) {
            case 'KUNTAWARINGIN' : $name = 'KUTAWARINGIN'; break;
            case 'KOTABARU' : $name = 'KOTA BARU'; break;
            case 'KEDUNGWARINGIN'  : $name = 'KEDUNG WARINGIN'; break;
            case 'TAJUR HALANG' : $name = 'TAJURHALANG' ; break;
            case 'KARANGBAHAGIA'  : $name = 'KARANG BAHAGIA'; break;
            case 'KELAPA NUNGGAL' : $name = 'KLAPANUNGGAL' ; break;
            case 'SOLOKAN JERUK' : $name = 'SOLOKANJERUK'; break;
            case 'PURBASARI' : $name = 'PURWASARI'; break;
            case 'WARUNGDOYONGA' : $name = 'WARUDOYONG'; break;
            case 'SUSUKANLEBAK' : $name = 'SUSUKAN LEBAK'; break;
            case 'KEDOKANBUNDER' : $name = 'KEDOKAN BUNDER'; break;
            case 'TALAGASARI' : $name = 'TELAGASARI'; break;
            case 'PARUNGPOTENG' : $name = 'PARUNGPONTENG'; break;
            case 'BLUBUR LIMBANGAN' : $name = 'BL. LIMBANGAN'; break;
            case 'CIKAKAP' : $name = 'CIKAKAK'; break;
            case 'PONDOK SALAM' : $name = 'PONDOKSALAM'; break;
            case 'GUNUNGTANJUNG' : $name = 'GUNUNG TANJUNG'; break;
            case 'KARANGKANCANA' : $name = 'KARANG KANCANA'; break;
            case 'CAMPAKA MULYA' : $name = 'CAMPAKAMULYA'; break;
            case 'PAMAGER SARI' : $name = 'PAMEGARSARI'; break;
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
            case 'BENGLE' :
                $name = 'BENGLE';
                if ($districtId == in_array($districtId, ['32.15.21'])) {
                    $name = 'BANGLE';
                }
                break;
            case 'BALUNGBANGJAYA' : $name = 'BALUMBANG JAYA'; break;
            case 'CEMPAKAMEKAR' : $name = 'CAMPAKA MEKAR'; break;
            case 'CISARANTEN KIDUL' : $name = 'CISANTREN KIDUL'; break;
            case 'CIKAMPEK UTARA' : $name = 'CILKAMPEK UTARA'; break;
            case 'CILEDUK' : $name = 'CILEDUG'; break;
            case 'CIHIDEUNG ILIR' : $name = 'CIHIDEUNGHILIR'; break;
            case 'CIMANGGU 1' : $name = 'CIMANGGU I'; break;
            case 'CIMANGGU 2' : $name = 'CIMANGGU II'; break;
            case 'CIBATOK 1' : $name = 'CIBATOK I'; break;
            case 'CIMINCRANG' : $name = 'CIMENERANG'; break;
            case 'CIBORELANG' : $name = 'CIBOLERANG'; break;
            case 'CIMEKAR' :
                $name = $subdistrict;
                if ($districtId == in_array($districtId, ['32.01.01'])) {
                    $name = 'CIRIMEKAR';
                }
                break;
            case 'HAURNGOMBONG' : $name = 'HAURGOMBONG'; break;
            case 'HARJAMEKAR' : $name = 'HAJARMEKAR'; break;
            case 'KELAPA NUNGGAL' : $name = 'KLAPANUNGGAL'; break;
            case 'KEBUN JAYANTI' : $name = 'KEBON JAYANTI'; break;
            case 'KARSAMENAK' : $name = 'KERSAMENAK'; break;
            case 'KALANGSURIA' : $name = 'KALANGSURYA'; break;
            case 'KADUMANGU' : $name = 'KADUMANGGU'; break;
            case 'KARANGPAWITAN' :
                $name = $subdistrict;
                if ($districtId == in_array($districtId, ['32.15.01'])) {
                    $name = 'KARAWANGPAWITAN';
                }
                break;
            case 'LEUWINAGGUNG' : $name = 'LEUWINANGGUNG'; break;
            case 'MANJAHLEGA' : $name = 'MUNJAHLEGA'; break;
            case 'MEKARJAYA' :
                $name = 'MEKARMULYA';
                if ($districtId == in_array($districtId, ['32.11.18', '32.01.29','32.04.13', '32.17.04', '32.07.32', '32.08.22', '32.08.22', '32.03.09', '32.02.21'])) {
                    $name = 'MEKARJAYA';
                }
                break;
            case 'MERUYUNG' : $name = 'MARUYUNG'; break;
            case 'MARACANG' : $name = 'MARANCANG'; break;
            case 'MARGUHURIP' : $name = 'MARGAHURIP'; break;
            case 'NAGERI KALER' : $name = 'NAGRI TENGAH'; break;
            case 'NAGERI KIDUL' : $name = 'NAGRI KIDUL'; break;
            case 'NAGERI TENGAH' : $name = 'NAGRI TENGAH'; break;
            case 'SULAEMAN' : $name = 'SULAIMAN'; break;
            case 'SAWANGAN LAMA' : $name = 'SAWANGAN'; break;
            case 'MANGGUNGHARJA' : $name = 'MANGUNHARJA'; break;
            case 'PAMAGER SARI' : $name = 'PAMEGARSARI'; break;
            case 'PASIRANJUNG' : $name = 'PASIRNANJUNG'; break;
            case 'PALAWAD' : $name = 'PLAWAD'; break;
            case 'PAKEMITANKIDUL' : $name = 'PAKAMITANKIDUL'; break;
            case 'PONDOK KASO TONGGOH' : $name = 'PONDOKASO TONGGOH'; break;
            case 'PASIRRANJI' : $name = 'PASIRANJI'; break;
            case 'SETU SARI' : $name = 'SITUSARI'; break;
            case 'SUDAJAYA GIRANG' : $name = 'SUNDAJAYAGIRANG'; break;
            case 'SUKAMANTRI' :
                $name = $subdistrict;
                if ($districtId == in_array($districtId, ['32.05.01'])) {
                    $name = 'SUKAMENTRI';
                }
                break;
            case 'TEMIYANGSARI' : $name = 'TAMIYANGSARI'; break;
            case 'TEGALTAMAN' : $name = 'TAGAL TAMAN'; break;
            case 'TEGALMUNJUL' : $name = 'TEGALMUNCUL'; break;
            case 'TALAGASARI' :
                $name = 'TELAGASARI';
                if ($districtId == in_array($districtId, ['32.05.10'])) {
                    $name = 'TALAGASARI';
                }
                break;
            case 'SUMBERREJA' : $name = 'SUMBEREJA'; break;
            case 'SINARSARI' : $name = 'SIRNASARI'; break;
            case 'SIGARACIPTA' : $name = 'SAGARACIPTA'; break;
            case 'SEGARAMAKMUR' : $name = 'SAGARA MAKMUR'; break;
            case 'PEKANTINGAN' : $name = 'PAKANTINGAN'; break;
            case 'PASIR BUNCIR' : $name = 'BASIR BUNCIR'; break;
            case 'PANGURAGAN LOR' : $name = 'PANGURANGAN LOR'; break;
            case 'PADANAAN' : $name = 'PADANAN'; break;
            case 'NANGGERANG' :
                $name = 'NANGERANG';
                if ($districtId == in_array($districtId, ['32.01.37', '32.11.12', '32.02.16', '32.08.12',])) {
                    $name = 'NANGGERANG';
                }
                break;
            case 'MANGKURAYAT' : $name = 'MANGURAKYAT'; break;
            case 'LINGGASARI' :
                $name = 'LINGGARSARI';
                if ($districtId == in_array($districtId, ['32.07.01'])) {
                    $name = 'LINGGASARI';
                }
                break;
            case 'LINGGAJATI' : $name = 'LINGGARJATI'; break;
            case 'KERTABASUKI' : $name = 'KARTABASUKI'; break;
            case 'KEBONKELAPA' : $name = 'KEBON KALAPA'; break;
            case 'KARANGMUKTI' : $name = 'KARYAMUKTI'; break;
            case 'CIHOWE' : $name = 'CIHOE'; break;
            case 'CIARUTEN ILIR' : $name = 'CIARUTEUN ILIR'; break;
            case 'BOJONGSARI LAMA' : $name = 'BOJONGSARI'; break;
            case 'BATEMBAT' : $name = 'BATTEMBAT'; break;
            default : $name = $subdistrict;
        }

        $n = str_replace(' ', '', $name);
        return [
            'id' => Subdistrict::whereRaw("replace(name,' ', '') = '$n'")->where('district_id', $districtId)->value('id'),
            'name' => $subdistrict,
        ];
    }
}
