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
            case 'ABADIJAYA': $name = 'ABADI JAYA'; break;
            case 'AREN JAYA': $name = 'ARENJAYA'; break;

            case 'BABAKAN ANYAR': $name = 'BABAKANANYAR'; break;
            case 'BABAKAN MANJETI': $name = 'BABAKANMANJETI'; break;
            case 'BABELAN KOTA': $name = 'BABELANKOTA'; break;

            case 'BANJAR SARI' :
                $name = 'BANJAR SARI';
                if (in_array($districtId, ['32.01.24'])) {
                    $name = 'BANJARSARI';
                }
                break;

            case 'BEKASI JAYA': $name = 'BEKASIJAYA'; break;
            case 'BINTARA JAYA': $name = 'BINTARAJAYA'; break;
            case 'BABAKANPEUTEUY': $name = 'BABAKAN PEUTEUY'; break;
            case 'BABAKAN SARI': $name = 'BABAKANSARI'; break;
            case 'BAKTI JAYA' : $name = 'BAKTIJAYA'; break;
            case 'BALUNGBANGJAYA' : $name = 'BALUMBANG JAYA'; break;
            case 'BALONG GEDE' : $name = 'BALONGGEDE'; break;
            case 'BOJONG BARU' : $name = 'BOJONGBARU'; break;
            case 'BOJONG GEDE' : $name = 'BOJONGGEDE'; break;
            case 'BOJONG INDAH' : $name = 'BOJONGINDAH'; break;
            case 'BOJONG KOKOSAN' : $name = 'BOJONGKOKOSAN'; break;
            case 'BOJONGKIDUL' : $name = 'BOJONG KIDUL'; break;
            case 'BOJONGSARI LAMA' : $name = 'BOJONGSARI'; break;

            case 'CURUGMEKAR' : $name = 'CURUG MEKAR'; break;


            case 'CIMANGGU 1' : $name = 'CIMANGGU I'; break;
            case 'CIMANGGU 2' : $name = 'CIMANGGU II'; break;
            case 'CIHANJUANG RAHAYU' : $name = 'CIHANJUANGRAHAYU'; break;
            case 'CILEDUK' : $name = 'CILEDUG'; break;
            case 'CIHAURGEULIS' : $name = 'CIHAUR GEULIS'; break;
            case 'CIHIDEUNG UDIK' : $name = 'CIHIDEUNGUDIK'; break;
            case 'CIHIDEUNG ILIR' :
                $name = 'CIHIDEUNG HILIR';
                if (in_array($districtId, ['32.01.15'])) {
                    $name = 'CIHIDEUNGHILIR';
                }
                break;
            case 'CIMEKAR' :
                $name = 'CIRIMEKAR';
                if ($districtId == '32.04.05') {
                    $name = 'CIMEKAR';
                }
                break;


            case 'DUREN JAYA' : $name = 'DURENJAYA'; break;
            case 'GEMPOL SARI' : $name = 'GEMPOLSARI'; break;
            case 'GEMPOLKOLOT' : $name = 'GEMPOL KOLOT'; break;
            case 'GUNUNG MENYAN' : $name = 'GUNUNGMENYAN'; break;
            case 'GUNUNG SARI' : $name = 'GUNUNGSARI'; break;
            case 'GUNUNG SINDUR' : $name = 'GUNUNGSINDUR'; break;
            case 'GUNUNGCUPU' : $name = 'GUNUNG CUPU'; break;
            case 'GUNUNGGEDE' : $name = 'GUNUNG GEDE'; break;
            case 'GUNUNGTANDALA' : $name = 'GUNUNG TANDALA'; break;

            case 'JATI SARI' : $name = 'JATISARI'; break;
            case 'JAKA MULYA' : $name = 'JAKAMULYA'; break;
            case 'KEBON GEDANG' : $name = 'KEBONGEDANG'; break;
            case 'KEBON WARU' : $name = 'KEBONWARU'; break;
            case 'KEDUNG WARINGIN' : $name = 'KEDUNGWARINGIN'; break;
            case 'LEBAK GEDE' : $name = 'LEBAKGEDE'; break;
            case 'WARINGIN JAYA' : $name = 'WARINGINJAYA'; break;
            case 'RANCA BUNGUR' : $name = 'RANCABUNGUR'; break;
            case 'SADANG SERANG' : $name = 'SADANGSERANG'; break;
            case 'HARAPAN JAYA' :
                $name = 'HARAPAN JAYA';
                if (in_array($districtId, ['32.01.01', '32.75.03'])) {
                    $name = 'HARAPANJAYA';
                }
                break;
            case 'LIMUS NUNGGAL' : $name = 'LIMUSNUNGGAL'; break;
            case 'MEKAR RAHAYU' : $name = 'MEKARRAHAYU'; break;
            case 'MEKAR WANGI' : $name = 'MEKARWANGI'; break;
            case 'NAGERI KALER' : $name = 'NAGRIKALER'; break;
            case 'NAGERI KIDUL' : $name = 'NAGRIKIDUL'; break;
            case 'NAGERI TENGAH' : $name = 'NAGRITENGAH'; break;
            case 'PANGKALANJATI' : $name = 'PANGKALAN JATI'; break;
            case 'PANGKALANJATI BARU' : $name = 'PANGGKALAN JATI BARU'; break;
            case 'PASIRKALIKI' :
                $name = 'PASIR KALIKI';
                if ($districtId == in_array($districtId, ['32.77.03', '32.15.18'])) {
                    $name = 'PASIRKALIKI';
                }
                break;
            case 'PONDOK KASO LANDEUH' : $name = 'PONDOKKASO LANDEUH'; break;
            case 'PONDOK KASO TONGGOH' : $name = 'PONDOKKASO Tonggoh'; break;
            case 'PUSAKARAKYAT' : $name = 'PUSAKA RAKYAT'; break;

            case 'RAJAMANDALA KULON' : $name = 'RAJAMANDALAKULON'; break;
            case 'RANCA SENGGANG' : $name = 'RANCASENGGANG'; break;
            case 'RATUJAYA' : $name = 'RATU JAYA'; break;
            case 'RAWA PANJANG' : $name = 'RAWAPANJANG'; break;

            case 'SAWANGAN LAMA' : $name = 'SAWANGAN'; break;
            case 'SULAEMAN' : $name = 'SULAIMAN'; break;
            case 'SUKAMAJUKALER' : $name = 'SUKAMAJU KALER'; break;
            case 'SUKA MENAK' : $name = 'SUKAMENAK'; break;
            case 'SETU SARI' : $name = 'SITUSARI'; break;
            case 'SUMUR BATU' : $name = 'SUMURBATU'; break;
            case 'SUKAMANTRI' :
                $name = 'SUKAMENTRI';
                if ($districtId == in_array($districtId, ['32.11.20', '32.01.31', '32.02.29', '32.06.36', '32.04.35'])) {
                    $name = 'SUKAMANTRI';
                }

                break;
            case 'TANAHBARU' :
                $name = 'TANAHBARU';
                if ($districtId == in_array($districtId, ['32.71.05'])) {
                    $name = 'TANAH BARU';
                }
                break;
            case 'TAJUR HALANG' :
                $name = 'TAJURHALANG';
                if ($districtId == '32.01.28') {
                    $name = 'TAJUR HALANG';
                }
                break;

            case 'TAMAN SARI' :
                $name = 'TAMANSARI';
                if ($districtId == in_array($districtId, ['32.16.18'])) {
                    $name = 'TAMAN SARI';
                }
                break;
            case 'TEGALGUNDIL' : $name = 'TEGAL GUNDIL'; break;
            case 'TEGAL PANJANG' : $name = 'TEGALPANJANG'; break;
            case 'TEGALSAWAH' : $name = 'TEGAL SAWAH'; break;
            case 'TRIDAYASAKTI' : $name = 'TRIDAYA SAKTI'; break;
            case 'TUGU JAYA' : $name = 'TUGUJAYA'; break;
            case 'WATES JAYA' : $name = 'WATESJAYA'; break;
            default : $name = $subdistrict;
        }

        return [
            'id' => Subdistrict::where('name', $name)->where('district_id', $districtId)->value('id'),
            'name' => $name,
        ];
    }
}
