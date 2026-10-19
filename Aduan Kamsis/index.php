<?php
// ==========================================
// 1. DATABASE SETUP & CONNECTION
// ==========================================
$host = 'localhost';
$user = 'root';
$pass = ''; // Default XAMPP MySQL password is empty
$db_name = 'kamsis_pms';

// Connect to MySQL server
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Auto-create database if it doesn't exist
$conn->query("CREATE DATABASE IF NOT EXISTS $db_name");
$conn->select_db($db_name);

// Auto-create the aduan (complaints) table if it doesn't exist
$table_sql = "CREATE TABLE IF NOT EXISTS aduan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_pelajar VARCHAR(100) NOT NULL,
    no_matrik VARCHAR(20) NOT NULL,
    no_telefon VARCHAR(20) NOT NULL,
    kamsis VARCHAR(20) NOT NULL,
    blok_bilik VARCHAR(50) NOT NULL,
    tingkat VARCHAR(10) NOT NULL,
    kategori VARCHAR(50) NOT NULL,
    keterangan TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'Baru',
    tarikh TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($table_sql);

// Auto-update table if the new columns don't exist yet (Seamless upgrade)
$check_col = $conn->query("SHOW COLUMNS FROM aduan LIKE 'no_telefon'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE aduan ADD COLUMN no_telefon VARCHAR(20) NOT NULL AFTER no_matrik");
    $conn->query("ALTER TABLE aduan ADD COLUMN kamsis VARCHAR(20) NOT NULL AFTER no_telefon");
    $conn->query("ALTER TABLE aduan ADD COLUMN tingkat VARCHAR(10) NOT NULL AFTER blok_bilik");
}

// Auto-update table for Evidence Image/Video upload
$check_img_col = $conn->query("SHOW COLUMNS FROM aduan LIKE 'gambar_bukti'");
if ($check_img_col && $check_img_col->num_rows == 0) {
    $conn->query("ALTER TABLE aduan ADD COLUMN gambar_bukti VARCHAR(255) DEFAULT NULL AFTER keterangan");
}

// Auto-create the pengguna (users) table for Admin/Penyelia (for future use)
$users_sql = "CREATE TABLE IF NOT EXISTS pengguna (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    peranan VARCHAR(20) NOT NULL
)";
$conn->query($users_sql);

// ==========================================
// 2. HANDLE FORM SUBMISSION (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = $conn->real_escape_string($_POST['nama']);
    $matrik = $conn->real_escape_string($_POST['matrik']);
    $telefon = $conn->real_escape_string($_POST['no_telefon']);
    $kamsis = $conn->real_escape_string($_POST['kamsis']);

    // Combine Blok and No. Bilik into one string for the database
    $blok = $_POST['blok'];
    $no_bilik = $_POST['no_bilik'];
    $lokasi = $conn->real_escape_string($blok . ' (Bilik ' . $no_bilik . ')');

    $tingkat = $conn->real_escape_string($_POST['tingkat']);
    $kategori = $conn->real_escape_string($_POST['kategori']);
    $keterangan = $conn->real_escape_string($_POST['keterangan']);

    // Handle File Upload (Picture/Video Evidence)
    $gambar_bukti = "";
    if (isset($_FILES['gambar_bukti']) && $_FILES['gambar_bukti']['error'] == 0) {
        $target_dir = "uploads/";
        // Create the uploads folder automatically if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        // Generate a unique file name to prevent overwriting (e.g., bukti_1234abc.jpg)
        $file_extension = pathinfo($_FILES["gambar_bukti"]["name"], PATHINFO_EXTENSION);
        $new_filename = uniqid("bukti_") . "." . $file_extension;
        $target_file = $target_dir . $new_filename;

        // Move the file from temporary location to our uploads folder
        if (move_uploaded_file($_FILES["gambar_bukti"]["tmp_name"], $target_file)) {
            $gambar_bukti = $conn->real_escape_string($target_file);
        }
    }

    $insert_sql = "INSERT INTO aduan (nama_pelajar, no_matrik, no_telefon, kamsis, blok_bilik, tingkat, kategori, keterangan, gambar_bukti)
                   VALUES ('$nama', '$matrik', '$telefon', '$kamsis', '$lokasi', '$tingkat', '$kategori', '$keterangan', '$gambar_bukti')";

    if ($conn->query($insert_sql) === TRUE) {
        // Redirect with a success message flag
        header("Location: " . $_SERVER['PHP_SELF'] . "?status=success");
        exit();
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Aduan Kerosakan Kamsis PMS</title>
    <style>
        /* ==========================================
           3. EMBEDDED CSS STYLING
           ========================================== */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f4f7f6; color: #333; line-height: 1.6; }
        .container { max-width: 1000px; margin: 2rem auto; padding: 0 1rem; }

        header { text-align: center; background-color: #003366; color: white; padding: 2rem; border-radius: 8px 8px 0 0; position: relative; }
        .btn-login { position: absolute; top: 20px; right: 20px; background-color: #e67e22; color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; font-size: 0.9rem; font-weight: bold; transition: background-color 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .btn-login:hover { background-color: #d35400; }

        .form-section { background: white; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-radius: 0 0 8px 8px; }
        h2 { color: #003366; margin-bottom: 1.5rem; border-bottom: 2px solid #eee; padding-bottom: 0.5rem; }
        .form-group { margin-bottom: 1.2rem; }
        .form-row { display: flex; gap: 20px; }
        .form-col { flex: 1; }
        label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        input[type="text"], input[type="tel"], select, textarea, input[type="file"] { width: 100%; padding: 0.8rem; border: 1px solid #ccc; border-radius: 4px; }

        .radio-group { display: flex; gap: 20px; flex-wrap: wrap; padding: 0.5rem 0; }
        .radio-group label { display: inline-flex; align-items: center; font-weight: normal; cursor: pointer; }
        .radio-group input[type="radio"] { margin-right: 8px; width: auto; transform: scale(1.2); }

        .btn-submit { background-color: #e67e22; color: white; border: none; padding: 1rem 2rem; font-size: 1.1rem; border-radius: 4px; cursor: pointer; width: 100%; font-weight: bold; transition: background-color 0.3s; }
        .btn-submit:hover { background-color: #d35400; }

        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 15px; border-radius: 4px; margin-bottom: 20px; font-weight: bold; text-align: center; }

        @media (max-width: 600px) {
            .form-row { flex-direction: column; gap: 0; }
            .btn-login { position: static; display: inline-block; margin-bottom: 1rem; }
            header { padding: 1.5rem 1rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <header>
            <a href="login.php" class="btn-login">Log Masuk Staf &rarr;</a>
            <h1>Sistem Aduan Kerosakan Kamsis</h1>
            <p>Politeknik Muadzam Shah (PMS)</p>
        </header>

        <!-- FORM SECTION -->
        <section class="form-section">

            <!-- Success Message Alert -->
            <?php if(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
                <div class="alert-success">
                    Aduan anda telah berjaya dihantar! Pihak pengurusan akan mengambil tindakan segera.
                </div>
            <?php endif; ?>

            <h2>Borang Aduan Baharu</h2>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="nama">Nama Penuh:</label>
                    <input type="text" id="nama" name="nama" required>
                </div>

                <div class="form-group">
                    <label for="matrik">No. Pendaftaran (Matrik):</label>
                    <input type="text" id="matrik" name="matrik" required>
                </div>

                <div class="form-group">
                    <label for="no_telefon">No. Telefon:</label>
                    <input type="tel" id="no_telefon" name="no_telefon" required>
                </div>

                <div class="form-group">
                    <label>Jantina Kamsis:</label>
                    <div class="radio-group">
                        <label><input type="radio" name="kamsis" value="Lelaki" required> Asrama Lelaki</label>
                        <label><input type="radio" name="kamsis" value="Perempuan" required> Asrama Perempuan</label>
                    </div>
                </div>

                <div class="form-row">
                    <!-- BLOCK RADIO BUTTONS -->
                    <div class="form-group form-col">
                        <label>Blok Kamsis:</label>
                        <div class="radio-group">
                            <label><input type="radio" name="blok" value="Blok A" required> Blok A</label>
                            <label><input type="radio" name="blok" value="Blok B" required> Blok B</label>
                            <label><input type="radio" name="blok" value="Blok C" required> Blok C</label>
                            <label><input type="radio" name="blok" value="Blok D" required> Blok D</label>
                            <label><input type="radio" name="blok" value="Blok E" required> Blok E</label>
                        </div>
                    </div>

                    <!-- ROOM NUMBER INPUT -->
                    <div class="form-group form-col">
                        <label for="no_bilik">No. Bilik (Cth: 09):</label>
                        <input type="text" id="no_bilik" name="no_bilik" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Tingkat:</label>
                    <div class="radio-group">
                        <label><input type="radio" name="tingkat" value="G" required> Ground Floor (G)</label>
                        <label><input type="radio" name="tingkat" value="1" required> Tingkat 1</label>
                        <label><input type="radio" name="tingkat" value="2" required> Tingkat 2</label>
                        <label><input type="radio" name="tingkat" value="3" required> Tingkat 3</label>
                        <label><input type="radio" name="tingkat" value="4" required> Tingkat 4</label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="kategori">Kategori Kerosakan:</label>
                    <select id="kategori" name="kategori" required>
                        <option value="" disabled selected>-- Sila Pilih Kategori --</option>
                        <option value="Elektrik">Elektrik (Lampu, Kipas, Plug atau Soket)</option>
                        <option value="Paip">Paip & Tandas</option>
                        <option value="Perabot">Pintu Bilik</option>
                        <option value="Lain-lain">Lain-lain</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="keterangan">Keterangan Lanjut:</label>
                    <textarea id="keterangan" name="keterangan" rows="4" required></textarea>
                </div>

                <!-- FILE UPLOAD INPUT -->
                <div class="form-group">
                    <label for="gambar_bukti">Muat Naik Bukti Bergambar / Video (Wajib):</label>
                    <input type="file" id="gambar_bukti" name="gambar_bukti" accept="image/*,video/*" required>
                </div>

                <button type="submit" class="btn-submit">Hantar Aduan</button>
            </form>
        </section>
    </div>
</body>
</html>
<?php
// Close the database connection
$conn->close();
?>
