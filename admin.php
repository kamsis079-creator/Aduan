<?php
session_start();

// Security Check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$host = 'localhost';
$user = 'root';
$pass = '';
$db_name = 'kamsis_pms';

$conn = new mysqli($host, $user, $pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// HANDLE STATUS UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $aduan_id = (int)$_POST['aduan_id'];
    $status_baru = $conn->real_escape_string($_POST['status_baru']);

    $update_sql = "UPDATE aduan SET status = '$status_baru' WHERE id = $aduan_id";
    if ($conn->query($update_sql) === TRUE) {
        // Keep the current filter if it exists in the URL
        $redirect_url = "admin.php";
        if (isset($_GET['filter_kamsis'])) {
            $redirect_url .= "?filter_kamsis=" . urlencode($_GET['filter_kamsis']);
        }
        header("Location: " . $redirect_url);
        exit();
    }
}

// ==========================================
// FETCH STATISTICS FOR DASHBOARD CARDS (Global)
// ==========================================
$stat_total = $conn->query("SELECT COUNT(*) as cnt FROM aduan")->fetch_assoc()['cnt'];
$stat_baru = $conn->query("SELECT COUNT(*) as cnt FROM aduan WHERE status = 'Baru'")->fetch_assoc()['cnt'];
$stat_proses = $conn->query("SELECT COUNT(*) as cnt FROM aduan WHERE status = 'Dalam Proses'")->fetch_assoc()['cnt'];
$stat_selesai = $conn->query("SELECT COUNT(*) as cnt FROM aduan WHERE status = 'Selesai'")->fetch_assoc()['cnt'];

// ==========================================
// HANDLE TABLE FILTERING
// ==========================================
$filter_kamsis = isset($_GET['filter_kamsis']) ? $conn->real_escape_string($_GET['filter_kamsis']) : 'All';

$query_table = "SELECT * FROM aduan";
if ($filter_kamsis === 'Lelaki' || $filter_kamsis === 'Perempuan') {
    $query_table .= " WHERE kamsis = '$filter_kamsis'";
}
$query_table .= " ORDER BY kamsis ASC, blok_bilik ASC, tingkat ASC";

$result = $conn->query($query_table);
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Staf - Sistem Aduan Kamsis</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f4f7f6; color: #333; line-height: 1.6; }
        .container { max-width: 1300px; margin: 2rem auto; padding: 0 1rem; }

        header { text-align: center; background-color: #003366; color: white; padding: 2rem; border-radius: 8px 8px 0 0; position: relative; }

        /* Header Action Buttons */
        .header-actions { position: absolute; top: 20px; right: 20px; display: flex; flex-direction: column; gap: 10px; align-items: flex-end; }
        .btn-logout { background-color: #c0392b; color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; font-weight: bold; font-size: 0.9rem; transition: 0.3s; width: 100%; text-align: center; }
        .btn-logout:hover { background-color: #e74c3c; }
        .btn-report { background-color: #2980b9; color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; font-weight: bold; font-size: 0.9rem; transition: 0.3s; width: 100%; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .btn-report:hover { background-color: #3498db; }

        /* Dashboard Cards CSS */
        .dashboard-cards { display: flex; gap: 20px; margin-bottom: 2rem; margin-top: 2rem; }
        .card { flex: 1; background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-align: center; border-bottom: 5px solid #ccc; }
        .card h3 { color: #666; font-size: 1rem; margin-bottom: 10px; }
        .card .number { font-size: 2.5rem; font-weight: bold; color: #333; }

        /* Card Colors */
        .card.total { border-color: #003366; }
        .card.baru { border-color: #d63031; }
        .card.proses { border-color: #0984e3; }
        .card.selesai { border-color: #00b894; }

        .table-section { background: white; padding: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-radius: 8px; }

        /* Filter Styles */
        .table-header-row { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; padding-bottom: 0.5rem; margin-bottom: 1.5rem; }
        h2 { color: #003366; margin: 0; }
        .filter-form { display: flex; gap: 10px; align-items: center; }
        .filter-form select { padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-weight: bold; color: #333; }
        .btn-filter { background-color: #003366; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; transition: 0.3s;}
        .btn-filter:hover { background-color: #002244; }

        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; font-size: 0.95rem; }
        th { background-color: #f8f9fa; }

        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 0.85rem; font-weight: bold; display: inline-block; }
        .status-baru { background-color: #ffeaa7; color: #d63031; }
        .status-dalam-proses { background-color: #81ecec; color: #0984e3; }
        .status-selesai { background-color: #55efc4; color: #00b894; }

        .action-form { display: flex; gap: 5px; }
        .action-form select { padding: 5px; border: 1px solid #ccc; border-radius: 4px; }
        .btn-update { background-color: #27ae60; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-update:hover { background-color: #2ecc71; }

        /* Evidence Link Styling */
        .link-bukti { color: #0984e3; font-weight: bold; text-decoration: none; background: #e8f4fd; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem; display: inline-block;}
        .link-bukti:hover { background: #74b9ff; color: white; }

        @media (max-width: 768px) {
            .dashboard-cards { flex-direction: column; gap: 10px; }
            .header-actions { position: static; flex-direction: row; justify-content: center; margin-bottom: 15px; }
            .btn-logout, .btn-report { width: auto; }
            .table-header-row { flex-direction: column; align-items: flex-start; gap: 15px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <header>
            <div class="header-actions">
                <a href="logout.php" class="btn-logout">Log Keluar &times;</a>
                <a href="laporan.php" class="btn-report">&#128202; Laporan Bulanan</a>
            </div>
            <h1>Dashboard Staf & Penyelia</h1>
            <p>Pengurusan Aduan Kerosakan Kamsis PMS</p>
        </header>

        <!-- DASHBOARD STATISTICS CARDS -->
        <div class="dashboard-cards">
            <div class="card total">
                <h3>Jumlah Aduan</h3>
                <div class="number"><?php echo $stat_total; ?></div>
            </div>
            <div class="card baru">
                <h3>Aduan Baru</h3>
                <div class="number" style="color: #d63031;"><?php echo $stat_baru; ?></div>
            </div>
            <div class="card proses">
                <h3>Dalam Proses</h3>
                <div class="number" style="color: #0984e3;"><?php echo $stat_proses; ?></div>
            </div>
            <div class="card selesai">
                <h3>Selesai</h3>
                <div class="number" style="color: #00b894;"><?php echo $stat_selesai; ?></div>
            </div>
        </div>

        <!-- DATA TABLE SECTION -->
        <section class="table-section">
            <div class="table-header-row">
                <h2>Senarai Semua Aduan Kerosakan</h2>

                <!-- GENDER FILTER FORM -->
                <form action="" method="GET" class="filter-form">
                    <label for="filter_kamsis" style="margin: 0; font-weight: bold; color: #003366;">Tapis Asrama:</label>
                    <select name="filter_kamsis" id="filter_kamsis">
                        <option value="All" <?php if($filter_kamsis == 'All') echo 'selected'; ?>>Semua</option>
                        <option value="Lelaki" <?php if($filter_kamsis == 'Lelaki') echo 'selected'; ?>>Lelaki Sahaja</option>
                        <option value="Perempuan" <?php if($filter_kamsis == 'Perempuan') echo 'selected'; ?>>Perempuan Sahaja</option>
                    </select>
                    <button type="submit" class="btn-filter">Tapis</button>
                </form>
            </div>

            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Tarikh</th>
                            <th>Pelajar</th>
                            <th>No. Tel</th>
                            <th>Lokasi Kamsis</th>
                            <th>Kategori & Keterangan</th>
                            <th>Bukti</th>
                            <th>Status Semasa</th>
                            <th>Tindakan (Kemaskini)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($row['tarikh'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['nama_pelajar']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($row['no_matrik']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($row['no_telefon']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['kamsis']); ?></strong><br>
                                    <?php
                                        // Extract Block and Room Number
                                        $lokasi_parts = explode(' (Bilik ', $row['blok_bilik']);
                                        $blok_only = $lokasi_parts[0];
                                        $bilik_only = isset($lokasi_parts[1]) ? rtrim($lokasi_parts[1], ')') : '-';
                                    ?>
                                    <?php echo htmlspecialchars($blok_only); ?>, Tingkat <?php echo htmlspecialchars($row['tingkat']); ?>, no bilik: <?php echo htmlspecialchars($bilik_only); ?>.
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['kategori']); ?></strong><br>
                                    <?php echo nl2br(htmlspecialchars($row['keterangan'])); ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['gambar_bukti'])): ?>
                                        <a href="<?php echo htmlspecialchars($row['gambar_bukti']); ?>" target="_blank" class="link-bukti">&#128444; Lihat Fail</a>
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 0.85rem;">Tiada Fail</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php $status_class = 'status-' . str_replace(' ', '-', strtolower($row['status'])); ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <form action="" method="POST" class="action-form">
                                        <input type="hidden" name="aduan_id" value="<?php echo $row['id']; ?>">
                                        <select name="status_baru" required>
                                            <option value="Baru" <?php if($row['status'] == 'Baru') echo 'selected'; ?>>Baru</option>
                                            <option value="Dalam Proses" <?php if($row['status'] == 'Dalam Proses') echo 'selected'; ?>>Dalam Proses</option>
                                            <option value="Selesai" <?php if($row['status'] == 'Selesai') echo 'selected'; ?>>Selesai</option>
                                        </select>
                                        <button type="submit" name="update_status" class="btn-update">Simpan</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" style="text-align:center;">Tiada aduan direkodkan untuk tapisan ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</body>
</html>
<?php $conn->close(); ?>
