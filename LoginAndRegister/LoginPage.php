<?php
/* Session & bootstrap */
session_start();

// If already logged in, send straight to Browse
if (isset($_SESSION['user_id'])) {
    header('Location: ../Browse/Browse.php');
    exit;
}

require_once __DIR__ . '/../data/database.php';   // Provides $pdo
 
/* Shared helpers */
function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
 
/* Login handler */
$loginErrors = [];
 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
 
    $email    = trim($_POST['loginEmail']    ?? '');
    $password = $_POST['loginPassword']      ?? '';   // NEVER trim passwords — alters the value being verified
 
    // Validation
    if ($email === '') {
        $loginErrors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $loginErrors[] = 'Invalid email format.';
    }
    if ($password === '') {
        $loginErrors[] = 'Password is required.';
    }
 
    // DB look-up only if validation passed
    if (empty($loginErrors)) {
        try {
            $stmt = $pdo->prepare(
                'SELECT userID, username, password_hash FROM accounts WHERE email = :email LIMIT 1'
            );
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
 
            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']  = $user['userID'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email']    = $email;
                
                /* Log audit: user logged in */
                // Function = logAudit(), Class = data/database.php
                logAudit($pdo, $user['userID'], 'login', 'User Login', 'User successfully logged in');
 
                header('Location: ../Browse/Browse.php');
                exit;
            } else {
                $loginErrors[] = 'Incorrect email or password.';
            }
 
        } catch (PDOException $e) {
            $loginErrors[] = 'DB Error: ' . $e->getMessage();
            error_log('Login PDO error: ' . $e->getMessage());
        }
    }
}
 
// Register handler
$registerErrors = [];
$regUsername    = '';
$regEmail       = '';
 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
 
    $regUsername    = trim($_POST['registerUsername']   ?? '');
    $regEmail       = trim($_POST['registerEmail']      ?? '');
    $regPassword    = $_POST['registerPassword']        ?? '';   // NEVER trim passwords
    $regConfirmPass = $_POST['registerConfirmPassword'] ?? '';
 
    // Validation 
    if ($regUsername === '') {
        $registerErrors[] = 'Username is required.';
    } elseif (strlen($regUsername) < 3 || strlen($regUsername) > 30) {
        $registerErrors[] = 'Username must be between 3 and 30 characters.';
    } elseif (!preg_match('/^[\w\-]+$/', $regUsername)) {
        $registerErrors[] = 'Username may only contain letters, numbers, underscores, and hyphens.';
    }
 
    if ($regEmail === '') {
        $registerErrors[] = 'Email is required.';
    } elseif (!filter_var($regEmail, FILTER_VALIDATE_EMAIL)) {
        $registerErrors[] = 'Invalid email format.';
    }
 
    if ($regPassword === '') {
        $registerErrors[] = 'Password is required.';
    } elseif (strlen($regPassword) < 8) {
        $registerErrors[] = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $regPassword)) {
        $registerErrors[] = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[0-9]/', $regPassword)) {
        $registerErrors[] = 'Password must contain at least one number.';
    }
 
    if ($regPassword !== $regConfirmPass) {
        $registerErrors[] = 'Passwords do not match.';
    }
 
    // DB checks & insert (only if all validation passed)
    if (empty($registerErrors)) {
        try {
            // Duplicate email check
            $stmt = $pdo->prepare('SELECT userID FROM accounts WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $regEmail]);
            if ($stmt->fetch()) {
                $registerErrors[] = 'An account with that email already exists.';
            }

            // Duplicate username check
            if (empty($registerErrors)) {
                $stmt = $pdo->prepare('SELECT userID FROM accounts WHERE username = :username LIMIT 1');
                $stmt->execute([':username' => $regUsername]);
                if ($stmt->fetch()) {
                    $registerErrors[] = 'That username is already taken.';
                }
            }

            // Insert new account
            if (empty($registerErrors)) {
                $hash = password_hash($regPassword, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare(
                    'INSERT INTO accounts (username, email, password_hash, created_at)
                     VALUES (:username, :email, :hash, NOW())'
                );
                $stmt->execute([
                    ':username' => $regUsername,
                    ':email'    => $regEmail,
                    ':hash'     => $hash,
                ]);

                $newId = (int) $pdo->lastInsertId();

                session_regenerate_id(true);
                $_SESSION['user_id']       = $newId;
                $_SESSION['username']      = $regUsername;
                $_SESSION['email']         = $regEmail;
                $_SESSION['setup_pending'] = true;

                /* Log audit: new account created */
                // Function = logAudit(), Class = data/database.php
                logAudit($pdo, $newId, 'created', 'New Account', "User registered: $regUsername ($regEmail)");

                header('Location: ../AccSetup/AccSetup.php');
                exit;
            }
        } catch (PDOException $e) {
            $registerErrors[] = 'DB Error: ' . $e->getMessage();
            error_log('Register PDO error: ' . $e->getMessage());
        }
    }
}
 
/* Which card to show on page reload */
$activeCard = (!empty($registerErrors)) ? 'register' : 'login';
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>TradeSA - Login / Register</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="LoginPage.css">
        <link rel="stylesheet" href="../data/preset.css">

        <style>
            
            input:-webkit-autofill,
            input:-webkit-autofill:hover, 
            input:-webkit-autofill:focus, 
            input:-webkit-autofill:active {
                -webkit-box-shadow: 0 0 0 1000px var(--timberwolf-gray) inset !important;
                -webkit-text-fill-color: var(--text-color) !important;
                transition: background-color 5000s ease-in-out 0s;
            }

        </style>

    </head>
    <body>
    
        <div class="page-bg"></div>
    
        <div class="panel" id="auth-panel">
    
            <div class="language-switcher">
                <select id="language-select">
                    <option value="en">English</option>
                    <option value="af">Afrikaans</option>
                    <option value="zu">Zulu</option>
                    <option value="xh">Xhosa</option>
                    <option value="nso">Northern Sotho</option>
                </select>
            </div>
    
            <div class="panel-content">
    
                <!-- LOGIN CARD -->
                <form action="LoginPage.php"
                    method="POST"
                    class="form-card <?= $activeCard === 'login' ? 'visible' : '' ?>"
                    id="login-card">
    
                    <input type="hidden" name="action" value="login">
                    <h2>Login</h2>
    
                    <?php if (!empty($loginErrors)): ?>
                        <div class="error-messages" role="alert">
                            <?php foreach ($loginErrors as $err): ?>
                                <p><?= h($err) ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
    
                    <div class="input-group">
                        <div class="input-row">
                            <label for="loginEmail-Input">
                                <img src="../img/mail_24dp_E3E3E3_FILL0_wght400_GRAD0_opsz24.svg" alt="Mail icon" width="38" height="38">
                            </label>
                            <input type="email"
                                name="loginEmail"
                                id="loginEmail-Input"
                                placeholder="Email"
                                autocomplete="email"
                                value="<?= h(trim($_POST['loginEmail'] ?? '')) ?>"
                                required>
                        </div>
                        <div class="input-row">
                            <label for="loginPassword-Input">
                                <img src="../img/lock_24dp_E3E3E3_FILL0_wght400_GRAD0_opsz24.svg" alt="Lock icon" width="38" height="38">
                            </label>
                            <input type="password"
                                name="loginPassword"
                                id="loginPassword-Input"
                                placeholder="Password"
                                autocomplete="current-password"
                                required>
                        </div>
                    </div>
    
                    <button id="login-submit" type="submit" class="primary">Login</button>
                    <button class="link" id="show-register" type="button">Don't have an account?</button>
                </form>
    
    
                <!-- REGISTER CARD -->
                <form action="LoginPage.php"
                    method="POST"
                    class="form-card <?= $activeCard === 'register' ? 'visible' : '' ?>"
                    id="register-card">
    
                    <input type="hidden" name="action" value="register">
                    <h2>Register</h2>
    
                    <?php if (!empty($registerErrors)): ?>
                        <div class="error-messages" role="alert">
                            <?php foreach ($registerErrors as $err): ?>
                                <p><?= h($err) ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
    
                    <div class="input-group">
                        <div class="input-row">
                            <label for="registerUsername-Input">
                                <img src="../img/account_circle_24dp_E3E3E3_FILL0_wght400_GRAD0_opsz24.svg" alt="Account icon" width="38" height="38">
                            </label>
                            <input type="text"
                                name="registerUsername"
                                id="registerUsername-Input"
                                placeholder="Username"
                                autocomplete="username"
                                value="<?= h($regUsername) ?>"
                                required>
                        </div>
                        <div class="input-row">
                            <label for="registerEmail-Input">
                                <img src="../img/mail_24dp_E3E3E3_FILL0_wght400_GRAD0_opsz24.svg" alt="Mail icon" width="38" height="38">
                            </label>
                            <input type="email"
                                name="registerEmail"
                                id="registerEmail-Input"
                                placeholder="Email"
                                autocomplete="email"
                                value="<?= h($regEmail) ?>"
                                required>
                        </div>
                        <div class="input-row">
                            <label for="registerPassword-Input">
                                <img src="../img/lock_24dp_E3E3E3_FILL0_wght400_GRAD0_opsz24.svg" alt="Lock icon" width="38" height="38">
                            </label>
                            <input type="password"
                                name="registerPassword"
                                id="registerPassword-Input"
                                placeholder="Password"
                                autocomplete="new-password"
                                required>
                        </div>
                        <div class="input-row">
                            <label for="registerConfirmPassword-Input">
                                <img src="../img/lock_24dp_E3E3E3_FILL0_wght400_GRAD0_opsz24.svg" alt="Lock icon" width="38" height="38">
                            </label>
                            <input type="password"
                                name="registerConfirmPassword"
                                id="registerConfirmPassword-Input"
                                placeholder="Confirm Password"
                                autocomplete="new-password"
                                required>
                        </div>
                    </div>
    
                    <button id="register-submit" type="submit" class="primary">Create account</button>
                    <button class="link" id="show-login" type="button">Already have an account?</button>
                </form>
    
            </div>
        </div>
    
        <script>
            const panel          = document.getElementById('auth-panel');
            const loginCard      = document.getElementById('login-card');
            const registerCard   = document.getElementById('register-card');
            const showRegister   = document.getElementById('show-register');
            const showLogin      = document.getElementById('show-login');
            const languageSelect = document.getElementById('language-select');
    
            const translations = {
                en:  { login: 'Login', register: 'Register', showRegister: "Don't have an account?", showLogin: 'Already have an account?', emailPh: 'Email', passPh: 'Password', userPh: 'Username', confirmPh: 'Confirm Password' },
                af:  { login: 'Teken in', register: 'Registreer', showRegister: 'Het jy nie een nie?', showLogin: 'Het jy reeds een?', emailPh: 'E-pos', passPh: 'Wagwoord', userPh: 'Gebruikersnaam', confirmPh: 'Bevestig wagwoord' },
                zu:  { login: 'Ngena ngemvume', register: 'Bhalisa', showRegister: 'Awunayo i-akhawunti?', showLogin: 'Usunayo i-akhawunti?', emailPh: 'Imeyili', passPh: 'Iphasiwedi', userPh: 'Igama lomsebenzisi', confirmPh: 'Qinisekisa iphasiwedi' },
                xh:  { login: 'Ngena', register: 'Bhalisa', showRegister: 'Awunakho i-akhawunti?', showLogin: 'Unayo i-akhawunti?', emailPh: 'I-imeyile', passPh: 'Ipasiwedi', userPh: 'Igama lomsebenzisi', confirmPh: 'Qinisekisa ipasiwedi' },
                nso: { login: 'Tsena', register: 'Ngwadiša', showRegister: 'Ga o na akhaonte?', showLogin: 'O na le akhaonte?', emailPh: 'Emeili', passPh: 'Phasewete', userPh: 'Leina la modiriši', confirmPh: 'Netefatša phasewete' }
            };
    
            function applyLanguage(lang) {
                const t = translations[lang] || translations.en;
                loginCard.querySelector('h2').textContent    = t.login;
                registerCard.querySelector('h2').textContent = t.register;
                showRegister.textContent = t.showRegister;
                showLogin.textContent    = t.showLogin;
                languageSelect.value     = lang;
                document.getElementById('loginEmail-Input').placeholder              = t.emailPh;
                document.getElementById('loginPassword-Input').placeholder           = t.passPh;
                document.getElementById('registerUsername-Input').placeholder        = t.userPh;
                document.getElementById('registerEmail-Input').placeholder           = t.emailPh;
                document.getElementById('registerPassword-Input').placeholder        = t.passPh;
                document.getElementById('registerConfirmPassword-Input').placeholder = t.confirmPh;
            }
    
            function swapCard(target) {
                const current = target === 'register' ? loginCard    : registerCard;
                const next    = target === 'register' ? registerCard : loginCard;
                if (next.classList.contains('visible')) return;
                panel.classList.add('slide-away');
                panel.addEventListener('transitionend', function awayHandler(e) {
                    if (e.propertyName !== 'transform') return;
                    panel.removeEventListener('transitionend', awayHandler);
                    current.classList.remove('visible');
                    next.classList.add('visible');
                    panel.classList.remove('slide-away');
                    void panel.offsetWidth;
                    panel.classList.add('slide-back');
                    panel.addEventListener('transitionend', function backHandler(e2) {
                        if (e2.propertyName !== 'transform') return;
                        panel.removeEventListener('transitionend', backHandler);
                        panel.classList.remove('slide-back');
                    });
                });
            }
    
            showRegister.addEventListener('click', () => swapCard('register'));
            showLogin.addEventListener('click',    () => swapCard('login'));
    
            // Client-side validation mirrors server rules exactly
            registerCard.addEventListener('submit', function(e) {
                const username = document.getElementById('registerUsername-Input').value.trim();
                const email    = document.getElementById('registerEmail-Input').value.trim();
                const pass     = document.getElementById('registerPassword-Input').value;
                const confirm  = document.getElementById('registerConfirmPassword-Input').value;
                const errs     = [];
    
                if (username.length < 3)                        errs.push('Username must be at least 3 characters.');
                if (!/^[\w\-]+$/.test(username))                errs.push('Username may only contain letters, numbers, _ or -.');
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errs.push('Invalid email format.');
                if (pass.length < 8)                            errs.push('Password must be at least 8 characters.');
                if (!/[A-Z]/.test(pass))                        errs.push('Password needs at least one uppercase letter.');
                if (!/[0-9]/.test(pass))                        errs.push('Password needs at least one number.');
                if (pass !== confirm)                           errs.push('Passwords do not match.');
    
                if (errs.length > 0) {
                    e.preventDefault();
                    let box = this.querySelector('.error-messages');
                    if (!box) {
                        box = document.createElement('div');
                        box.className = 'error-messages';
                        box.setAttribute('role', 'alert');
                        this.querySelector('h2').after(box);
                    }
                    box.innerHTML = errs.map(m => `<p>${m}</p>`).join('');
                }
            });
    
            loginCard.addEventListener('submit', function(e) {
                const email = document.getElementById('loginEmail-Input').value.trim();
                const pass  = document.getElementById('loginPassword-Input').value;
                const errs  = [];
    
                if (!email)                                        errs.push('Please enter your email.');
                else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errs.push('Invalid email format.');
                if (!pass)                                         errs.push('Please enter your password.');
    
                if (errs.length > 0) {
                    e.preventDefault();
                    let box = this.querySelector('.error-messages');
                    if (!box) {
                        box = document.createElement('div');
                        box.className = 'error-messages';
                        box.setAttribute('role', 'alert');
                        this.querySelector('h2').after(box);
                    }
                    box.innerHTML = errs.map(m => `<p>${m}</p>`).join('');
                }
            });
    
            const savedLanguage = localStorage.getItem('selectedLanguage') || 'en';
            applyLanguage(savedLanguage);
            languageSelect.addEventListener('change', function() {
                applyLanguage(this.value);
                localStorage.setItem('selectedLanguage', this.value);
            });
    
            window.addEventListener('load', () => setTimeout(() => panel.classList.add('visible-block'), 80));
        </script>
    </body>
</html>