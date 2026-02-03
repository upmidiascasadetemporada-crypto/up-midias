<?php
// adm.php
// Painel de login - cria DB/admin padrão se necessário
session_start();

// Config
$dbDir = __DIR__ . '/data';
$dbFile = $dbDir . '/db.sqlite';
$defaultAdminUser = 'admin';
$defaultAdminPass = 'senha123'; // troque após primeiro login

// Garante diretório data e conecta
if (!is_dir($dbDir)) mkdir($dbDir, 0777, true);
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Cria tabelas se não existirem
$pdo->exec("
CREATE TABLE IF NOT EXISTS admins (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT UNIQUE NOT NULL,
  password TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS posts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT,
  location TEXT,
  price TEXT,
  description TEXT,
  images TEXT,
  created_at TEXT
);
");

// Insere admin padrão se não existir
$stmt = $pdo->prepare('SELECT COUNT(*) FROM admins WHERE username = ?');
$stmt->execute([$defaultAdminUser]);
if ($stmt->fetchColumn() == 0) {
    $hash = password_hash($defaultAdminPass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO admins (username, password) VALUES (?, ?)');
    $stmt->execute([$defaultAdminUser, $hash]);
}

// Logout (opcional via GET)
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: adm.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($user === '' || $pass === '') {
        $error = 'Informe usuário e senha.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([$user]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && password_verify($pass, $row['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $row['username'];
            header('Location: post.php');
            exit;
        } else {
            $error = 'Usuário ou senha inválidos.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin - Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>body{height:100vh;display:flex;align-items:center;justify-content:center;background:#f4f6f8;} .card{max-width:420px;width:100%}</style>
</head>
<body>
  <div class="card shadow-sm">
    <div class="card-body">
      <h4 class="card-title mb-3">Painel Administrativo</h4>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <form method="post" action="">
        <div class="mb-3">
          <label class="form-label">Usuário</label>
          <input name="username" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label">Senha</label>
          <input name="password" type="password" class="form-control" required>
        </div>
        <div class="d-grid">
          <button class="btn btn-primary">Entrar</button>
        </div>
      </form>
      <hr>
      <small class="text-muted">
        Usuário padrão: <strong><?php echo $defaultAdminUser; ?></strong> — Senha padrão: <strong><?php echo $defaultAdminPass; ?></strong><br>
        (Altere a senha após o primeiro login.)
      </small>
    </div>
  </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
