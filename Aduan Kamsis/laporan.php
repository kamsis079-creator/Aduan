<?php
session_start();

// Security Check: Only logged-in staff can view the report
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// ==========================================
// 1. DATABASE SETUP & CONNECTION
// ==========================================
$host = 'localhost';
$user = 'root';
$pass = '';
$db_name = 'kamsis_pms';

$conn = new mysqli($host, $user, $pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ==========================================
// 2. FETCH ALL DATA AND AGGREGATE IN PHP
// ==========================================
$all_aduan = $conn->query("SELECT * FROM aduan ORDER BY tarikh DESC");

// Initialize arrays for our reports
$laporan_bulan = [];
$laporan_kategori = ['Elektrik' => 0, 'Paip' => 0, 'Perabot' => 0, 'Lain-lain' => 0];
$laporan_kamsis = ['Lelaki' => [], 'Perempuan' => []];

// Initialize array for floors separated by gender
$laporan_tingkat = [
    'Lelaki' => ['G' => 0, '1' => 0, '2' => 0, '3' => 0, '4' => 0],
    'Perempuan' => ['G' => 0, '1' => 0, '2' => 0, '3' => 0, '4' => 0]
];

if ($all_aduan && $all_aduan->num_rows > 0) {
    while($row = $all_aduan->fetch_assoc()) {

        // --- 1. TALLY BY MONTH (UPDATED LOGIC) ---
        $bulan_tahun = date('Y-m', strtotime($row['tarikh']));
        if(!isset($laporan_bulan[$bulan_tahun])) {
            $laporan_bulan[$bulan_tahun] = [
                'total' => 0,
                'lelaki' => 0,
                'perempuan' => 0,
                'kategori' => [],
                'blok' => [],
                'tingkat' => []
            ];
        }

        $laporan_bulan[$bulan_tahun]['total']++;

        // Tally Gender per Month
        $kamsis = $row['kamsis'];
        if ($kamsis == 'Lelaki') {
            $laporan_bulan[$bulan_tahun]['lelaki']++;
        } elseif ($kamsis == 'Perempuan') {
            $laporan_bulan[$bulan_tahun]['perempuan']++;
        }

        // Tally Kategori per Month
        $kat = $row['kategori'];
        if(!isset($laporan_bulan[$bulan_tahun]['kategori'][$kat])) $laporan_bulan[$bulan_tahun]['kategori'][$kat] = 0;
        $laporan_bulan[$bulan_tahun]['kategori'][$kat]++;

        // Tally Blok per Month
        $blok_parts = explode(' (Bilik', $row['blok_bilik']);
        $blok = $blok_parts[0];
        if(!isset($laporan_bulan[$bulan_tahun]['blok'][$blok])) $laporan_bulan[$bulan_tahun]['blok'][$blok] = 0;
        $laporan_bulan[$bulan_tahun]['blok'][$blok]++;

        // Tally Tingkat per Month
        $tingkat = $row['tingkat'];
        if(!isset($laporan_bulan[$bulan_tahun]['tingkat'][$tingkat])) $laporan_bulan[$bulan_tahun]['tingkat'][$tingkat] = 0;
        $laporan_bulan[$bulan_tahun]['tingkat'][$tingkat]++;

        // --- 2. GLOBAL STATS (For Top Cards) ---
        if(isset($laporan_kategori[$kat])) {
            $laporan_kategori[$kat]++;
        } else {
            $laporan_kategori[$kat] = 1;
        }

        if(!isset($laporan_kamsis[$kamsis][$blok])) {
            $laporan_kamsis[$kamsis][$blok] = 0;
        }
        $laporan_kamsis[$kamsis][$blok]++;

        if(isset($laporan_tingkat[$kamsis][$tingkat])) {
            $laporan_tingkat[$kamsis][$tingkat]++;
        }
    }
}

// Sort the month array descending (Latest month first)
krsort($laporan_bulan);

// Array to translate month numbers to Malay
$senarai_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Mac',
    '04' => 'April', '05' => 'Mei', '06' => 'Jun',
    '07' => 'Julai', '08' => 'Ogos', '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Disember'
];

// ==========================================
// 3. HANDLE EXCEL EXPORT REQUEST
// ==========================================
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Laporan_Bulanan_Aduan_Kamsis.xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "<table border='1'>";
    echo "<tr>
            <th style='background-color: #003366; color: white;'>Bulan & Tahun</th>
            <th style='background-color: #003366; color: white;'>Jumlah Keseluruhan</th>
            <th style='background-color: #003366; color: white;'>Jumlah (Lelaki)</th>
            <th style='background-color: #003366; color: white;'>Jumlah (Perempuan)</th>
            <th style='background-color: #003366; color: white;'>Statistik Kategori Kerosakan</th>
            <th style='background-color: #003366; color: white;'>Statistik Mengikut Blok</th>
            <th style='background-color: #003366; color: white;'>Statistik Mengikut Aras</th>
          </tr>";

    if (!empty($laporan_bulan)) {
        foreach($laporan_bulan as $bulan_tahun => $data) {
            $parts = explode('-', $bulan_tahun);
            $nama_bulan_ms = $senarai_bulan[$parts[1]] . ' ' . $parts[0];

            // Format arrays into strings for Excel (mso-data-placement:same-cell keeps it in one box)
            $kat_arr = []; foreach($data['kategori'] as $k => $v) $kat_arr[] = "$k: $v";
            $str_kat = implode("<br style='mso-data-placement:same-cell;' />", $kat_arr);

            ksort($data['blok']);
            $blok_arr = []; foreach($data['blok'] as $b => $v) $blok_arr[] = "$b: $v";
            $str_blok = implode("<br style='mso-data-placement:same-cell;' />", $blok_arr);

            ksort($data['tingkat']);
            $tingkat_arr = []; foreach($data['tingkat'] as $t => $v) {
                $nama_tgkt = ($t == 'G') ? 'Ground' : "Tgkt $t";
                $tingkat_arr[] = "$nama_tgkt: $v";
            }
            $str_tingkat = implode("<br style='mso-data-placement:same-cell;' />", $tingkat_arr);

            echo "<tr>";
            echo "<td valign='top'>" . $nama_bulan_ms . "</td>";
            echo "<td valign='top' align='center'>" . $data['total'] . "</td>";
            echo "<td valign='top' align='center'>" . $data['lelaki'] . "</td>";
            echo "<td valign='top' align='center'>" . $data['perempuan'] . "</td>";
            echo "<td valign='top'>" . $str_kat . "</td>";
            echo "<td valign='top'>" . $str_blok . "</td>";
            echo "<td valign='top'>" . $str_tingkat . "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='7'>Tiada Rekod</td></tr>";
    }
    echo "</table>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penuh - Sistem Aduan Kamsis</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f4f7f6; color: #333; line-height: 1.6; }
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 1rem; }

        header { text-align: center; background-color: #003366; color: white; padding: 2rem; border-radius: 8px 8px 0 0; position: relative; }

        .header-buttons { position: absolute; top: 20px; right: 20px; display: flex; gap: 10px; }
        .btn-nav { background-color: #e67e22; color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; font-weight: bold; transition: 0.3s; }
        .btn-nav:hover { background-color: #d35400; }
        .btn-logout { background-color: #c0392b; color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; font-weight: bold; transition: 0.3s; }
        .btn-logout:hover { background-color: #e74c3c; }

        /* GRID FOR MINI REPORTS */
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 2rem 0; }
        .summary-card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-top: 4px solid #003366; }
        .summary-card h3 { color: #003366; border-bottom: 2px solid #eee; padding-bottom: 0.5rem; margin-bottom: 1rem; font-size: 1.1rem; }

        .mini-table { width: 100%; border-collapse: collapse; }
        .mini-table th, .mini-table td { padding: 8px; border-bottom: 1px solid #f1f2f6; text-align: left; }
        .mini-table th { color: #666; font-weight: 600; width: 70%; }
        .mini-table td { font-weight: bold; color: #e67e22; text-align: right; }
        .sub-header { background-color: #f8f9fa; font-style: italic; color: #555 !important; }

        /* MAIN MONTHLY TABLE */
        .table-section { background: white; padding: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-radius: 8px; margin-bottom: 2rem; }

        .table-header-row { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; padding-bottom: 0.5rem; margin-bottom: 1.5rem; }
        .table-header-row h2 { color: #003366; margin: 0; border: none; padding: 0; }
        .btn-export { background-color: #27ae60; color: white; text-decoration: none; padding: 8px 15px; border-radius: 4px; font-weight: bold; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-export:hover { background-color: #2ecc71; }

        .main-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .main-table th, .main-table td { padding: 12px; text-align: center; border-bottom: 1px solid #ddd; font-size: 0.95rem; vertical-align: top;}
        .main-table th { background-color: #f8f9fa; color: #003366; }

        .col-bulan { text-align: left !important; font-weight: bold; font-size: 1.1rem; }
        .col-total { font-weight: bold; background-color: #f1f2f6; font-size: 1.2rem;}
        .text-left { text-align: left !important; }

        @media (max-width: 768px) {
            .header-buttons { position: static; justify-content: center; margin-bottom: 15px; }
            header { padding: 1.5rem 1rem; }
            .table-header-row { flex-direction: column; gap: 15px; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <header>
            <div class="header-buttons">
                <a href="admin.php" class="btn-nav">&larr; Kembali Dashboard</a>
                <a href="logout.php" class="btn-logout">Log Keluar &times;</a>
            </div>
            <h1>Laporan Penuh Kerosakan Kamsis</h1>
            <p>Politeknik Muadzam Shah (PMS)</p>
        </header>

        <!-- TOP SUMMARY GRID -->
        <div class="summary-grid">

            <!-- CARD 1: KATEGORI -->
            <div class="summary-card">
                <h3>Statistik Kategori Kerosakan</h3>
                <table class="mini-table">
                    <?php foreach($laporan_kategori as $kat => $jumlah): ?>
                    <tr>
                        <th><?php echo htmlspecialchars($kat); ?></th>
                        <td><?php echo $jumlah; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <!-- CARD 2: BLOK & JANTINA -->
            <div class="summary-card">
                <h3>Statistik Mengikut Blok</h3>
                <table class="mini-table">
                    <!-- Lelaki -->
                    <tr><th colspan="2" class="sub-header">Asrama Lelaki</th></tr>
                    <?php
                        if(!empty($laporan_kamsis['Lelaki'])) {
                            ksort($laporan_kamsis['Lelaki']); // Sort Block A-Z
                            foreach($laporan_kamsis['Lelaki'] as $blok => $jumlah) {
                                echo "<tr><th>" . htmlspecialchars($blok) . "</th><td>{$jumlah}</td></tr>";
                            }
                        } else {
                            echo "<tr><th>Tiada Rekod</th><td>0</td></tr>";
                        }
                    ?>

                    <!-- Perempuan -->
                    <tr><th colspan="2" class="sub-header" style="border-top: 2px solid #ddd;">Asrama Perempuan</th></tr>
                    <?php
                        if(!empty($laporan_kamsis['Perempuan'])) {
                            ksort($laporan_kamsis['Perempuan']); // Sort Block A-Z
                            foreach($laporan_kamsis['Perempuan'] as $blok => $jumlah) {
                                echo "<tr><th>" . htmlspecialchars($blok) . "</th><td>{$jumlah}</td></tr>";
                            }
                        } else {
                            echo "<tr><th>Tiada Rekod</th><td>0</td></tr>";
                        }
                    ?>
                </table>
            </div>

            <!-- CARD 3: TINGKAT SEPARATED BY GENDER -->
            <div class="summary-card">
                <h3>Statistik Mengikut Aras (Tingkat)</h3>
                <table class="mini-table">
                    <!-- Lelaki -->
                    <tr><th colspan="2" class="sub-header">Asrama Lelaki</th></tr>
                    <tr><th>Ground Floor (G)</th><td><?php echo $laporan_tingkat['Lelaki']['G']; ?></td></tr>
                    <tr><th>Tingkat 1</th><td><?php echo $laporan_tingkat['Lelaki']['1']; ?></td></tr>
                    <tr><th>Tingkat 2</th><td><?php echo $laporan_tingkat['Lelaki']['2']; ?></td></tr>
                    <tr><th>Tingkat 3</th><td><?php echo $laporan_tingkat['Lelaki']['3']; ?></td></tr>
                    <tr><th>Tingkat 4</th><td><?php echo $laporan_tingkat['Lelaki']['4']; ?></td></tr>

                    <!-- Perempuan -->
                    <tr><th colspan="2" class="sub-header" style="border-top: 2px solid #ddd;">Asrama Perempuan</th></tr>
                    <tr><th>Ground Floor (G)</th><td><?php echo $laporan_tingkat['Perempuan']['G']; ?></td></tr>
                    <tr><th>Tingkat 1</th><td><?php echo $laporan_tingkat['Perempuan']['1']; ?></td></tr>
                    <tr><th>Tingkat 2</th><td><?php echo $laporan_tingkat['Perempuan']['2']; ?></td></tr>
                    <tr><th>Tingkat 3</th><td><?php echo $laporan_tingkat['Perempuan']['3']; ?></td></tr>
                    <tr><th>Tingkat 4</th><td><?php echo $laporan_tingkat['Perempuan']['4']; ?></td></tr>
                </table>
            </div>

        </div>

        <!-- BOTTOM DATA TABLE (MONTHLY SUMMARY) -->
        <section class="table-section">
            <div class="table-header-row">
                <h2>Rekod Aduan Mengikut Bulan</h2>

                <!-- EXCEL DOWNLOAD BUTTON -->
                <a href="laporan.php?export=excel" class="btn-export">&#128190; Muat Turun Excel</a>
            </div>

            <div style="overflow-x: auto;">
                <table class="main-table">
                    <thead>
                        <tr>
                            <th style="text-align: left;">Bulan & Tahun</th>
                            <th>Jum. Keseluruhan</th>
                            <th style="color: #0984e3;">Jum. Lelaki</th>
                            <th style="color: #e84393;">Jum. Perempuan</th>
                            <th>Statistik Kategori Kerosakan</th>
                            <th>Statistik Mengikut Blok</th>
                            <th>Statistik Mengikut Aras</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($laporan_bulan)): ?>
                            <?php foreach($laporan_bulan as $bulan_tahun => $data):
                                $parts = explode('-', $bulan_tahun);
                                $nama_bulan_ms = $senarai_bulan[$parts[1]] . ' ' . $parts[0];

                                // Format HTML Arrays
                                $kat_html = []; foreach($data['kategori'] as $k => $v) $kat_html[] = "<strong>$k:</strong> $v";
                                $str_kat = implode("<br>", $kat_html);

                                ksort($data['blok']);
                                $blok_html = []; foreach($data['blok'] as $b => $v) $blok_html[] = "<strong>$b:</strong> $v";
                                $str_blok = implode("<br>", $blok_html);

                                ksort($data['tingkat']);
                                $tingkat_html = []; foreach($data['tingkat'] as $t => $v) {
                                    $nama_tgkt = ($t == 'G') ? 'Ground' : "Tgkt $t";
                                    $tingkat_html[] = "<strong>$nama_tgkt:</strong> $v";
                                }
                                $str_tingkat = implode("<br>", $tingkat_html);
                            ?>
                            <tr>
                                <td class="col-bulan"><?php echo htmlspecialchars($nama_bulan_ms); ?></td>
                                <td class="col-total"><?php echo $data['total']; ?></td>
                                <td style="color: #0984e3; font-weight:bold;"><?php echo $data['lelaki']; ?></td>
                                <td style="color: #e84393; font-weight:bold;"><?php echo $data['perempuan']; ?></td>
                                <td class="text-left"><?php echo $str_kat; ?></td>
                                <td class="text-left"><?php echo $str_blok; ?></td>
                                <td class="text-left"><?php echo $str_tingkat; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align:center; font-style:italic; color: #888;">Tiada rekod laporan ditemui.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</body>
</html>
<?php $conn->close(); ?>
