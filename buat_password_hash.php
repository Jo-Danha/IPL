<?php
$password_hash = '';
$pesan = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['password_baru'])) {
    $password_baru = $_POST['password_baru'];
    // Membuat hash dari password baru menggunakan algoritma default yang aman
    $password_hash = password_hash($password_baru, PASSWORD_DEFAULT);
    $pesan = "Hash berhasil dibuat untuk password: '<strong>" . htmlspecialchars($password_baru) . "</strong>'";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Buat Password Hash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md bg-white p-8 rounded-lg shadow-md">
        <h1 class="text-2xl font-bold mb-6 text-center text-gray-800">Generator Hash Password</h1>
        <p class="text-sm text-gray-600 mb-4 text-center">
            Gunakan alat ini untuk membuat hash password baru. Setelah itu, salin hash yang dihasilkan dan perbarui kolom 'password' di tabel 'users' pada database Anda.
        </p>
        
        <form method="POST" action="">
            <div class="mb-4">
                <label for="password_baru" class="block text-gray-700 text-sm font-bold mb-2">Password Baru:</label>
                <input type="text" name="password_baru" id="password_baru" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded w-full">Buat Hash</button>
        </form>

        <?php if ($password_hash): ?>
        <div class="mt-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
            <p class="font-bold"><?php echo $pesan; ?></p>
            <p class="mt-2 text-sm">Hash Anda:</p>
            <textarea class="w-full bg-white p-2 mt-1 rounded border resize-none" rows="3" readonly onclick="this.select()"><?php echo htmlspecialchars($password_hash); ?></textarea>
            <p class="text-xs mt-2">Klik pada kolom di atas untuk memilih semua, lalu salin (Ctrl+C atau Cmd+C).</p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
