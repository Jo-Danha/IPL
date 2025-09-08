<?php
/**
 * ============================================================================
 * FILE: api.php
 * VERSI: 8.3.0 (Penyempurnaan Login & Penambahan Fitur Ganti Password)
 *
 * DESKRIPSI:
 * Backend API yang direstrukturisasi total untuk Aplikasi Manajemen IPL.
 * Menggunakan pendekatan fungsional untuk kejelasan dan pemeliharaan.
 * Setiap 'action' dari frontend dipetakan ke fungsi spesifik.
 * ============================================================================
 */

// -----------------------------------------------------------------------------
// SETUP & KONFIGURASI
// -----------------------------------------------------------------------------
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
session_start();
require 'db_connect.php';

// -----------------------------------------------------------------------------
// FUNGSI HELPER
// -----------------------------------------------------------------------------

/** Mengirim respons JSON dan menghentikan eksekusi. */
function send_json_response($data) {
    echo json_encode($data);
    exit;
}

/** Memproses file yang diunggah dan mengubahnya menjadi format base64. */
function file_to_base64($file) {
    if ($file && $file['error'] === UPLOAD_ERR_OK && !empty($file['tmp_name'])) {
        $content = file_get_contents($file['tmp_name']);
        $type = mime_content_type($file['tmp_name']);
        return 'data:' . $type . ';base64,' . base64_encode($content);
    }
    return null;
}

/** Menyimpan atau memperbarui satu baris pengaturan di database. */
function update_setting($conn, $key, $value) {
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->bind_param("ss", $key, $value);
    $success = $stmt->execute();
    $stmt->close();
    if (!$success) {
        throw new Exception("Gagal menyimpan pengaturan untuk kunci: $key");
    }
}

// -----------------------------------------------------------------------------
// ROUTER & KONTROL AKSES
// -----------------------------------------------------------------------------
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$public_actions = ['login', 'logout'];

if (!in_array($action, $public_actions) && (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true)) {
    http_response_code(401);
    send_json_response(['success' => false, 'message' => 'Akses ditolak. Sesi tidak valid.']);
}

// Memetakan 'action' ke fungsi yang sesuai
$action_map = [
    'login' => 'handle_login',
    'logout' => 'handle_logout',
    'get_all_data' => 'handle_get_all_data',
    'add_warga' => 'handle_add_warga',
    'update_warga' => 'handle_update_warga',
    'delete_warga' => 'handle_delete_warga',
    'import_warga' => 'handle_import_warga',
    'update_payment' => 'handle_update_payment',
    'bulk_update_payment' => 'handle_bulk_update_payment',
    'add_transaksi' => 'handle_add_transaksi',
    'update_transaksi' => 'handle_update_transaksi',
    'delete_transaksi' => 'handle_delete_transaksi',
    'add_iuran' => 'handle_add_iuran',
    'delete_iuran' => 'handle_delete_iuran',
    'save_admin_settings' => 'handle_save_admin_settings',
    'save_laporan_settings' => 'handle_save_laporan_settings',
    'save_keuangan_settings' => 'handle_save_keuangan_settings',
    'change_password' => 'handle_change_password',
];

if (isset($action_map[$action]) && function_exists($action_map[$action])) {
    call_user_func($action_map[$action], $conn);
} else {
    http_response_code(400);
    send_json_response(['success' => false, 'message' => 'Aksi tidak valid atau tidak ditemukan.']);
}

// -----------------------------------------------------------------------------
// FUNGSI OTENTIKASI
// -----------------------------------------------------------------------------

function handle_login($conn) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $user['id'];
            send_json_response(['success' => true]);
        }
    }
    send_json_response(['success' => false, 'message' => 'Username atau password salah.']);
}

function handle_logout($conn) {
    session_destroy();
    send_json_response(['success' => true, 'message' => 'Logout berhasil.']);
}

function handle_change_password($conn) {
    $user_id = $_SESSION['user_id'];
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($current_pass, $user['password'])) {
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update_stmt->bind_param("si", $new_hash, $user_id);
        $update_stmt->execute();
        send_json_response(['success' => true, 'message' => 'Password berhasil diubah.']);
    } else {
        send_json_response(['success' => false, 'message' => 'Password saat ini salah.']);
    }
}


// -----------------------------------------------------------------------------
// FUNGSI PENGAMBILAN DATA
// -----------------------------------------------------------------------------

function handle_get_all_data($conn) {
    $year = $_POST['year'] ?? date('Y');
    $user_id = $_SESSION['user_id'];
    $response = [];

    $stmt_admin = $conn->prepare("SELECT nama_lengkap as name, profile_pic as profilePic FROM users WHERE id = ?");
    $stmt_admin->bind_param("i", $user_id);
    $stmt_admin->execute();
    $response['adminData'] = $stmt_admin->get_result()->fetch_assoc() ?: ['name' => 'Admin', 'profilePic' => null];

    $settings_res = $conn->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = $settings_res->fetch_assoc()) $settings[$row['setting_key']] = $row['setting_value'];
    
    $response['appSettings']['appTitle'] = $settings['app_title'] ?? 'Aplikasi IPL';
    $response['appSettings']['appFavicon'] = $settings['app_favicon'] ?? null;
    $response['saldoAwalTahun'] = (float)($settings['saldo_awal_tahun'] ?? 0);

    $laporan_keys = ['nama_paguyuban', 'fs_paguyuban', 'alamat', 'fs_alamat', 'ketua', 'bendahara', 'nomor_surat', 'alignment', 'line_spacing', 'logo_kiri', 'logo_kanan', 'ttd_ketua', 'ttd_bendahara', 'text_before_a', 'text_after_a', 'text_before_b', 'text_after_b', 'text_after_table_b', 'text_before_c', 'text_after_c', 'text_catatan', 'text_final_notes'];
    foreach ($laporan_keys as $key) {
        $camelCaseKey = lcfirst(str_replace('_', '', ucwords($key, '_')));
        $response['laporanSettings'][$camelCaseKey] = $settings['laporan_'.$key] ?? null;
    }

    $response['iuranComponents'] = $conn->query("SELECT id, nama, jumlah FROM iuran_components ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
    $warga_res = $conn->query("SELECT id, nama, unit FROM warga ORDER BY LEFT(unit, 1), CAST(SUBSTRING(unit, 2) AS UNSIGNED)");
    $wargaData = [];
    while($warga = $warga_res->fetch_assoc()) {
        $warga['pembayaran'] = array_fill(1, 12, 0);
        $wargaData[$warga['id']] = $warga;
    }
    
    if (!empty($wargaData)) {
        $stmt_pembayaran = $conn->prepare("SELECT warga_id, bulan, jumlah_bayar FROM pembayaran WHERE tahun = ?");
        $stmt_pembayaran->bind_param("i", $year);
        $stmt_pembayaran->execute();
        $pembayaran_res = $stmt_pembayaran->get_result();
        while ($p = $pembayaran_res->fetch_assoc()) {
            if (isset($wargaData[$p['warga_id']])) {
                $wargaData[$p['warga_id']]['pembayaran'][(int)$p['bulan']] = (float)$p['jumlah_bayar'];
            }
        }
    }
    $response['wargaData'] = array_values($wargaData);

    // [FITUR BARU] Kalkulasi dan sisipkan total iuran bulanan ke dalam data transaksi
    $monthly_iuran_totals = array_fill(1, 12, 0);
    foreach ($response['wargaData'] as $warga) {
        foreach ($warga['pembayaran'] as $bulan => $jumlah) {
            $monthly_iuran_totals[(int)$bulan] += (float)$jumlah;
        }
    }
    
    $iuran_transaksi = [];
    $bulan_names = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
    foreach ($monthly_iuran_totals as $bulan => $total) {
        if ($total > 0) {
            $iuran_transaksi[] = [
                'id' => 'iuran_' . $bulan,
                'tanggal' => $year . '-' . str_pad($bulan, 2, '0', STR_PAD_LEFT) . '-01',
                'item' => 'Penerimaan Iuran ' . $bulan_names[$bulan-1],
                'debit' => $total,
                'kredit' => 0,
                'lampiran' => null,
                'keterangan' => 'Total iuran dari warga yang telah membayar.',
                'is_generated' => true
            ];
        }
    }
    $transaksi_manual = $conn->query("SELECT id, tanggal, item, debit, kredit, lampiran, keterangan FROM transaksi ORDER BY tanggal DESC, id DESC")->fetch_all(MYSQLI_ASSOC);
    $response['transaksiData'] = array_merge($iuran_transaksi, $transaksi_manual);
    usort($response['transaksiData'], function($a, $b) {
        return strtotime($b['tanggal']) - strtotime($a['tanggal']);
    });
    
    send_json_response(['success' => true, 'data' => $response]);
}

// -----------------------------------------------------------------------------
// FUNGSI CRUD (Create, Read, Update, Delete)
// -----------------------------------------------------------------------------

function handle_add_warga($conn) {
    $stmt = $conn->prepare("INSERT INTO warga (nama, unit) VALUES (?, ?)");
    $stmt->bind_param("ss", $_POST['nama'], $_POST['unit']);
    if ($stmt->execute()) send_json_response(['success' => true, 'id' => $conn->insert_id]);
    else send_json_response(['success' => false, 'message' => $stmt->error]);
}

function handle_update_warga($conn) {
    $stmt = $conn->prepare("UPDATE warga SET nama = ?, unit = ? WHERE id = ?");
    $stmt->bind_param("ssi", $_POST['nama'], $_POST['unit'], $_POST['id']);
    if ($stmt->execute()) send_json_response(['success' => true]);
    else send_json_response(['success' => false, 'message' => $stmt->error]);
}

function handle_delete_warga($conn) {
    $stmt = $conn->prepare("DELETE FROM warga WHERE id = ?");
    $stmt->bind_param("i", $_POST['id']);
    if ($stmt->execute()) send_json_response(['success' => true]);
    else send_json_response(['success' => false, 'message' => $stmt->error]);
}

function handle_update_payment($conn) {
    $stmt = $conn->prepare("INSERT INTO pembayaran (warga_id, tahun, bulan, jumlah_bayar) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE jumlah_bayar = VALUES(jumlah_bayar)");
    $stmt->bind_param("iiid", $_POST['warga_id'], $_POST['year'], $_POST['month'], $_POST['amount']);
    if ($stmt->execute()) send_json_response(['success' => true]);
    else send_json_response(['success' => false, 'message' => $stmt->error]);
}

function handle_add_transaksi($conn) {
    $tipe = $_POST['tipe'] ?? 'kredit'; $jumlah = (float)($_POST['jumlah'] ?? 0);
    $debit = ($tipe === 'debit') ? $jumlah : 0; $kredit = ($tipe === 'kredit') ? $jumlah : 0;
    $lampiran = file_to_base64($_FILES['lampiran'] ?? null);
    $stmt = $conn->prepare("INSERT INTO transaksi (tanggal, item, debit, kredit, lampiran, keterangan) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssddss", $_POST['tanggal'], $_POST['item'], $debit, $kredit, $lampiran, $_POST['keterangan']);
    if ($stmt->execute()) send_json_response(['success' => true, 'id' => $conn->insert_id]);
    else send_json_response(['success' => false, 'message' => $stmt->error]);
}

function handle_update_transaksi($conn) {
    $tipe = $_POST['tipe']; $jumlah = (float)$_POST['jumlah'];
    $debit = ($tipe === 'debit') ? $jumlah : 0; $kredit = ($tipe === 'kredit') ? $jumlah : 0;
    $lampiran = file_to_base64($_FILES['lampiran'] ?? null);
    $remove_lampiran = isset($_POST['remove_lampiran']) && $_POST['remove_lampiran'] === 'true';

    $sql = "UPDATE transaksi SET tanggal=?, item=?, debit=?, kredit=?, keterangan=?";
    $types = "ssdds"; $params = [$_POST['tanggal'], $_POST['item'], $debit, $kredit, $_POST['keterangan']];
    
    if ($remove_lampiran) { $sql .= ", lampiran=NULL"; }
    elseif ($lampiran) { $sql .= ", lampiran=?"; $types .= "s"; $params[] = $lampiran; }
    
    $sql .= " WHERE id=?"; $types .= "i"; $params[] = $_POST['id'];
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) send_json_response(['success' => true]);
    else send_json_response(['success' => false, 'message' => $stmt->error]);
}

function handle_delete_transaksi($conn) {
    $stmt = $conn->prepare("DELETE FROM transaksi WHERE id = ?");
    $stmt->bind_param("i", $_POST['id']);
    if ($stmt->execute()) send_json_response(['success' => true]);
    else send_json_response(['success' => false, 'message' => $stmt->error]);
}

function handle_add_iuran($conn) {
    $stmt = $conn->prepare("INSERT INTO iuran_components (nama, jumlah) VALUES (?, ?)");
    $stmt->bind_param("sd", $_POST['nama'], $_POST['jumlah']);
    if ($stmt->execute()) send_json_response(['success' => true, 'id' => $conn->insert_id]);
    else send_json_response(['success' => false, 'message' => $stmt->error]);
}

function handle_delete_iuran($conn) {
    $stmt = $conn->prepare("DELETE FROM iuran_components WHERE id = ?");
    $stmt->bind_param("i", $_POST['id']);
    if ($stmt->execute()) send_json_response(['success' => true]);
    else send_json_response(['success' => false, 'message' => $stmt->error]);
}

// -----------------------------------------------------------------------------
// FUNGSI AKSI MASAL (BULK) & IMPOR
// -----------------------------------------------------------------------------

function handle_bulk_update_payment($conn) {
    $res = $conn->query("SELECT id FROM warga");
    $warga_ids = array_column($res->fetch_all(MYSQLI_ASSOC), 'id');
    if (empty($warga_ids)) { send_json_response(['success' => true, 'message' => 'Tidak ada data warga.']); return; }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO pembayaran (warga_id, tahun, bulan, jumlah_bayar) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE jumlah_bayar = VALUES(jumlah_bayar)");
        foreach ($warga_ids as $warga_id) {
            $stmt->bind_param("iiid", $warga_id, $_POST['year'], $_POST['month'], $_POST['amount']);
            $stmt->execute();
        }
        $conn->commit();
        send_json_response(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        send_json_response(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handle_import_warga($conn) {
    $wargaData = json_decode($_POST['wargaData'], true);
    $year = $_POST['year'];

    $conn->begin_transaction();
    try {
        $conn->query("SET FOREIGN_KEY_CHECKS=0; TRUNCATE TABLE pembayaran; TRUNCATE TABLE warga; SET FOREIGN_KEY_CHECKS=1;");
        $stmtWarga = $conn->prepare("INSERT INTO warga (nama, unit) VALUES (?, ?)");
        $stmtPembayaran = $conn->prepare("INSERT INTO pembayaran (warga_id, tahun, bulan, jumlah_bayar) VALUES (?, ?, ?, ?)");
        
        foreach ($wargaData as $w) {
            $stmtWarga->bind_param("ss", $w['nama'], $w['unit']);
            $stmtWarga->execute();
            $warga_id = $conn->insert_id;
            foreach ($w['pembayaran'] as $bulan => $jumlah) {
                if ($jumlah > 0) { $stmtPembayaran->bind_param("iiid", $warga_id, $year, $bulan, $jumlah); $stmtPembayaran->execute(); }
            }
        }
        $conn->commit();
        send_json_response(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        send_json_response(['success' => false, 'message' => $e->getMessage()]);
    }
}


// -----------------------------------------------------------------------------
// FUNGSI PENYIMPANAN PENGATURAN
// -----------------------------------------------------------------------------

function handle_save_admin_settings($conn) {
    $user_id = $_SESSION['user_id'];
    $conn->begin_transaction();
    try {
        update_setting($conn, 'app_title', $_POST['app_title']);
        if (isset($_POST['remove_app_favicon']) && $_POST['remove_app_favicon'] === 'true') {
            update_setting($conn, 'app_favicon', null);
        } else if ($favicon = file_to_base64($_FILES['app_favicon'] ?? null)) {
            update_setting($conn, 'app_favicon', $favicon);
        }

        $stmt_user = $conn->prepare("UPDATE users SET nama_lengkap = ? WHERE id = ?");
        $stmt_user->bind_param("si", $_POST['admin_name'], $user_id);
        $stmt_user->execute();
        
        if (isset($_POST['remove_admin_pic']) && $_POST['remove_admin_pic'] === 'true') {
            $conn->query("UPDATE users SET profile_pic = NULL WHERE id = $user_id");
        } else if ($pic = file_to_base64($_FILES['admin_pic'] ?? null)) {
            $stmt_pic = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $stmt_pic->bind_param("si", $pic, $user_id);
            $stmt_pic->execute();
        }
        
        $conn->commit();
        send_json_response(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        send_json_response(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handle_save_laporan_settings($conn) {
    $conn->begin_transaction();
    try {
        $text_keys = ['laporan_nama_paguyuban', 'laporan_fs_paguyuban', 'laporan_alamat', 'laporan_fs_alamat', 'laporan_ketua', 'laporan_bendahara', 'laporan_nomor_surat', 'laporan_alignment', 'laporan_line_spacing', 'laporan_text_before_a', 'laporan_text_after_a', 'laporan_text_before_b', 'laporan_text_after_b', 'laporan_text_after_table_b', 'laporan_text_before_c', 'laporan_text_after_c', 'laporan_text_catatan', 'laporan_text_final_notes'];
        foreach ($text_keys as $key) if (isset($_POST[$key])) update_setting($conn, $key, $_POST[$key]);

        $file_keys = ['laporan_logo_kiri', 'laporan_logo_kanan', 'laporan_ttd_ketua', 'laporan_ttd_bendahara'];
        foreach ($file_keys as $key) {
            if (isset($_POST['remove_' . $key]) && $_POST['remove_' . $key] === 'true') {
                update_setting($conn, $key, null);
            } else if ($img = file_to_base64($_FILES[$key] ?? null)) {
                update_setting($conn, $key, $img);
            }
        }
        $conn->commit();
        send_json_response(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        send_json_response(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handle_save_keuangan_settings($conn) {
    try {
        update_setting($conn, 'saldo_awal_tahun', $_POST['saldo_awal_tahun']);
        send_json_response(['success' => true]);
    } catch (Exception $e) {
        send_json_response(['success' => false, 'message' => $e->getMessage()]);
    }
}

// -----------------------------------------------------------------------------
// PENUTUP KONEKSI
// -----------------------------------------------------------------------------
$conn->close();
?>

