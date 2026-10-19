<?php
session_start();

// ==========================================
// 1. DATABASE SETUP & CONNECTION
// ==========================================
$host = 'localhost';
$user = 'root';
$pass = ''; // Default XAMPP MySQL password is empty
$db_name = 'kamsis_pms';

$conn = new mysqli($host, $user, $pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Auto-create a default Admin account if the table is empty
$check_users = $conn->query("SELECT * FROM pengguna");
if ($check_users && $check_users->num_rows == 0) {
    // We encrypt the password for security using PHP's password_hash
    $default_pass = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO pengguna (username, password, peranan) VALUES ('admin', '$default_pass', 'Admin')");
}

$error_message = '';

// ==========================================
// 2. HANDLE LOGIN FORM SUBMISSION (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM pengguna WHERE username = '$username'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // Verify the encrypted password
        if (password_verify($password, $row['password'])) {
            // Login Success: Set session variables
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $row['username'];
            $_SESSION['peranan'] = $row['peranan'];

            // Redirect to admin dashboard
            header("Location: admin.php");
            exit();
        } else {
            $error_message = "Katalaluan tidak sah! Sila cuba lagi.";
        }
    } else {
        $error_message = "Pengguna tidak wujud!";
    }
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Masuk - Sistem Aduan Kamsis PMS</title>
    <style>
        /* ==========================================
           3. EMBEDDED CSS STYLING (SAME BASE DESIGN)
           ========================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background-color: #f4f7f6;
            color: #333;
            line-height: 1.6;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh; /* Centers the login box vertically */
        }
        .container {
            width: 100%;
            max-width: 450px; /* Narrower width for login page */
            padding: 0 1rem;
        }
        header {
            text-align: center;
            background-color: #003366; /* PMS Corporate Blue */
            color: white;
            padding: 2rem;
            border-radius: 8px 8px 0 0;
        }
        .form-section {
            background: white;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-radius: 0 0 8px 8px;
        }
        h2 {
            color: #003366;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #eee;
            padding-bottom: 0.5rem;
            text-align: center;
        }
        .form-group {
            margin-bottom: 1.2rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .btn-submit {
            background-color: #e67e22; /* PMS Orange */
            color: white;
            border: none;
            padding: 1rem;
            font-size: 1.1rem;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-weight: bold;
            transition: background-color 0.3s;
            margin-top: 10px;
        }
        .btn-submit:hover {
            background-color: #d35400;
        }
        .error-msg {
            background-color: #ffcccc;
            color: #cc0000;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #ff9999;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #003366;
            text-decoration: none;
            font-weight: 600;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <header>
            <h1>Sistem Aduan</h1>
            <p>Politeknik Muadzam Shah (PMS)</p>
        </header>

        <!-- FORM SECTION -->
        <section class="form-section">
            <h2>Log Masuk Staf</h2>

            <!-- Display Error Message if login fails -->
            <?php if ($error_message !== ''): ?>
                <div class="error-msg">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="form-group">
                    <label for="username">ID Pengguna (Username):</label>
                    <input type="text" id="username" name="username" required>
                </div>

                <div class="form-group">
                    <label for="password">Katalaluan (Password):</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="btn-submit">Log Masuk</button>

                <!-- Link back to the student homepage -->
                <a href="index.php" class="back-link">&larr; Kembali ke Halaman Utama</a>
            </form>
        </section>
    </div>
</body>
</html>
