<?php
/**
 * File ini digunakan untuk membuat koneksi ke database MySQL.
 * Ganti nilai variabel di bawah ini sesuai dengan pengaturan server Anda.
 */

// Pengaturan koneksi database
$db_host = 'localhost'; // Host database, biasanya 'localhost'
$db_user = 'root';      // Username database, default 'root'
$db_pass = '';          // Password database, default kosong
$db_name = 'ipl_management'; // Nama database yang akan digunakan

// Membuat koneksi ke database menggunakan mysqli
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Memeriksa apakah koneksi berhasil atau gagal
if ($conn->connect_error) {
    // Jika koneksi gagal, hentikan eksekusi dan tampilkan pesan error
    header('Content-Type: application/json');
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => "Koneksi Database Gagal: " . $conn->connect_error]));
}

// Mengatur character set koneksi ke utf8mb4
$conn->set_charset("utf8mb4");

?>
