<?php
declare(strict_types=1);

// 简易密码保护
$accessPassword = 'Ass669Oippw';
session_start();
if (isset($_POST['page_pwd'])) {
    if (hash_equals($accessPassword, $_POST['page_pwd'] ?? '')) {
        $_SESSION['gh_tool_auth'] = true;
    } else {
        $error = '密码错误';
    }
}
if (empty($_SESSION['gh_tool_auth'])) {
    ?>
    <!doctype html>
    <html lang="zh-CN">
    <head>
      <meta charset="UTF-8">
      <title>GitHub Fork 删除工具 - 登录</title>
      <style>
        body {font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}
        .card {background:#fff;padding:32px;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,.15);width:320px;text-align:center;}
        .card h2{margin:0 0 16px;}
        input[type=password]{width:100%;padding:12px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;margin-bottom:12px;}
        button{width:100%;padding:12px;background:#667eea;color:#fff;border:none;border-radius:8px;font-size:15px;cursor:pointer;}
        button:hover{background:#5568d3;}
        .err{color:#c33;margin-bottom:10px;}
      </style>
    </head>
    <body>
      <div class="card">
        <h2>访问验证</h2>
        <?php if (!empty($error)) echo '<div class="err">'.htmlspecialchars($error).'</div>'; ?>
        <form method="post">
          <input type="password" name="page_pwd" placeholder="请输入访问密码" required autofocus>
          <button type="submit">进入</button>
        </form>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// 工具逻辑
$result = null;
$errMsg = '';
$repos = [];

function ghRequest(string $method, string $url, string $token, ?array $data = null): array {
    $ch = curl_init($url);
    $headers = [
        'Accept: application/vnd.github+json',
        'Authorization: Bearer ' . $token,
        'User-Agent: gh-fork-cleaner'
    ];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$status, $body, $err];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $token = trim($_POST['token'] ?? '');
    $owner = trim($_POST['owner'] ?? '');
    if ($token === '' || $owner === '') {
        $errMsg = 'Token 和 Owner 均不能为空';
    } else {
        if ($_POST['action'] === 'list') {
            [$status, $body, $err] = ghRequest('GET', "https://api.github.com/users/{$owner}/repos?per_page=100&type=forks", $token);
            if ($err) $errMsg = $err;
            elseif ($status !== 200) $errMsg = "GitHub 返回状态 $status: $body";
            else {
                $repos = json_decode($body, true) ?: [];
                $repos = array_filter($repos, fn($r) => $r['fork'] === true);
            }
        } elseif ($_POST['action'] === 'delete' && !empty($_POST['repos'])) {
            $selected = $_POST['repos'];
            $result = [];
            foreach ($selected as $full) {
                [$status, $body, $err] = ghRequest('DELETE', "https://api.github.com/repos/{$full}", $token);
                if ($err) $result[] = "$full 删除失败：$err";
                elseif (in_array($status, [204,202,200])) $result[] = "$full 已提交删除";
                else $result[] = "$full 删除失败（HTTP $status）: $body";
            }
        }
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GitHub Fork 删除工具</title>
  <style>
    *{box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;}
    body{margin:0;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;padding:20px;}
    .container{max-width:980px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,.15);overflow:hidden;}
    .header{padding:24px 28px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;}
    .header h1{margin:0;font-size:26px;}
    .header p{margin:6px 0 0;opacity:.9;}
    .content{padding:24px 28px;}
    .row{display:flex;gap:16px;flex-wrap:wrap;}
    label{font-weight:600;color:#444;display:block;margin-bottom:6px;}
    input[type=text],input[type=password],textarea{width:100%;padding:12px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;}
    button{padding:12px 18px;border:none;border-radius:8px;background:#667eea;color:#fff;font-weight:600;cursor:pointer;transition:.2s;}
    button:hover{background:#5568d3;}
    .btn-secondary{background:#6c757d;} .btn-secondary:hover{background:#5a6268;}
    .card{background:#f8f9fa;border:1px solid #e9ecef;border-radius:10px;padding:16px;margin-top:16px;}
    .err{color:#c33;margin-bottom:10px;}
    table{width:100%;border-collapse:collapse;margin-top:12px;}
    th,td{border:1px solid #e9ecef;padding:10px;text-align:left;}
    th{background:#f5f5f5;}
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>GitHub Fork 删除工具</h1>
      <p>列出 Fork 仓库并批量删除（需要 GitHub Token）</p>
    </div>
    <div class="content">
      <?php if ($errMsg): ?><div class="err"><?php echo htmlspecialchars($errMsg); ?></div><?php endif; ?>
      <?php if ($result): ?>
        <div class="card">
          <strong>删除结果：</strong><br>
          <ul>
            <?php foreach ($result as $line): ?>
              <li><?php echo htmlspecialchars($line); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="action" value="list">
        <div class="row">
          <div style="flex:1;min-width:240px;">
            <label>GitHub Token (需有删除仓库权限)</label>
            <input type="password" name="token" required placeholder="ghp_xxx 或 PAT" value="<?php echo htmlspecialchars($_POST['token'] ?? ''); ?>">
          </div>
          <div style="flex:1;min-width:180px;">
            <label>Owner（你的用户名）</label>
            <input type="text" name="owner" required placeholder="your-github-username" value="<?php echo htmlspecialchars($_POST['owner'] ?? ''); ?>">
          </div>
          <div style="align-self:flex-end;">
            <button type="submit">列出 Fork 仓库</button>
          </div>
        </div>
      </form>

      <?php if ($repos): ?>
        <form method="post" style="margin-top:18px;">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="token" value="<?php echo htmlspecialchars($_POST['token'] ?? ''); ?>">
          <input type="hidden" name="owner" value="<?php echo htmlspecialchars($_POST['owner'] ?? ''); ?>">
          <div class="card">
            <strong>选择要删除的 Fork 仓库：</strong>
            <table>
              <tr><th>选择</th><th>仓库</th><th>描述</th></tr>
              <?php foreach ($repos as $r): ?>
                <tr>
                  <td><input type="checkbox" name="repos[]" value="<?php echo htmlspecialchars($r['full_name']); ?>"></td>
                  <td><?php echo htmlspecialchars($r['full_name']); ?></td>
                  <td><?php echo htmlspecialchars($r['description'] ?? ''); ?></td>
                </tr>
              <?php endforeach; ?>
            </table>
            <div style="margin-top:12px;">
              <button type="submit" class="btn-secondary" onclick="return confirm('确定删除选中的 Fork 仓库？');">删除所选</button>
            </div>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>

