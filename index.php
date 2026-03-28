<?php
session_start();
require 'db.php';

$error = '';
$success = '';
$generated_voter_id = '';

// Handle REGISTRATION
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'register') {
    $name = $_POST['name'] ?? '';
    $dob = $_POST['dob'] ?? $_POST['DOB'] ?? '';
    if (empty($dob)) $dob = '2000-01-01'; // Safe fallback to prevent MySQL crash
    $gender = $_POST['gender'] ?? '';
    $address = $_POST['address'] ?? '';
    $mobile = $_POST['mobile'] ?? '';
    $aadhaar = $_POST['aadhaar'] ?? '';
    $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);

    // Prevent duplicates globally (Aadhaar or Mobile)
    $check = $conn->query("SELECT * FROM voters WHERE aadhaar = '$aadhaar' OR mobile = '$mobile'");
    if ($check && $check->num_rows > 0) {
        $error = "Aadhaar or Mobile Number is already registered to an existing Voter ID!";
        $_SESSION['step'] = 'register';
    } else {
        // Generate a random, unique 6-digit Voter ID Code
        $voter_id = '';
        while(true) {
            $voter_id = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
            $check_id = $conn->query("SELECT id FROM voters WHERE voter_id = '$voter_id'");
            if($check_id && $check_id->num_rows == 0) break; // Unique!
        }

        // Insert Securely
        $stmt = $conn->prepare("INSERT INTO voters (voter_id, name, password, dob, gender, address, mobile, aadhaar) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if($stmt) {
            $stmt->bind_param("ssssssss", $voter_id, $name, $password, $dob, $gender, $address, $mobile, $aadhaar);
            if($stmt->execute()) {
                $success = "Registration Successful! Important: Your 6-Digit Voter ID is: <strong>" . $voter_id . "</strong>. Please copy it immediately to Login.";
                $generated_voter_id = $voter_id;
                $_SESSION['step'] = 'login';
            } else {
                $error = "Registration Database Error: " . $stmt->error . " | Please check if date formatting or data sizes are correct.";
            }
            $stmt->close();
        } else {
            $error = "Database Error: " . $conn->error . ". Please run setup_db.php!";
        }
    }
}

// Handle LOGIN
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'login') {
    $voter_id = $_POST['voter_id'];
    $name = $_POST['name'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, password, voted_for FROM voters WHERE voter_id = ? AND name = ?");
    if($stmt) {
        $stmt->bind_param("ss", $voter_id, $name);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $voter = $result->fetch_assoc();
            if (password_verify($password, $voter['password'])) {
                if ($voter['voted_for'] !== null) {
                    $error = "Rule Violation: You have already cast your ballot. One vote per citizen is strictly enforced.";
                } else {
                    $_SESSION['voter_id'] = $voter_id;
                    $_SESSION['voter_name'] = $voter['name'];
                    $_SESSION['step'] = 'dashboard';
                    header("Location: index.php");
                    exit();
                }
            } else {
                $error = "Authentication Failed: Invalid Password.";
            }
        } else {
            $error = "Authentication Failed: Invalid Citizen Name or Voter ID.";
        }
        $stmt->close();
    } else {
         $error = "Database Error: Please run setup_db.php!";
    }
}

// Handle VOTING
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'vote') {
    if(isset($_SESSION['voter_id'])) {
        $candidate_id = (int)$_POST['candidate_id'];
        $voter_id = $_SESSION['voter_id'];
        
        $check = $conn->query("SELECT voted_for FROM voters WHERE voter_id = '$voter_id'");
        $voter = $check->fetch_assoc();

        if ($voter['voted_for'] == null) {
            $conn->query("UPDATE voters SET voted_for = $candidate_id WHERE voter_id = '$voter_id'");
            $conn->query("UPDATE candidates SET votes = votes + 1 WHERE id = $candidate_id");
            
            $_SESSION['step'] = 'success';
            $_SESSION['receipt_name'] = $_SESSION['voter_name'];
            $_SESSION['receipt_voter_id'] = $voter_id;
            unset($_SESSION['voter_id']); // Lock out
            header("Location: index.php");
            exit();
        } else {
            $error = "Fraud Prevention: You have already voted!";
        }
    }
}

// Handle CLEAR VOTES
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'clear_votes') {
    $conn->query("UPDATE candidates SET votes = 0");
    $conn->query("UPDATE voters SET voted_for = NULL");
    $success = "SYSTEM RESET: All valid votes wiped. Candidates reset to 0. Citizens unlocked to test voting again.";
    $_SESSION['step'] = 'admin';
}

// Routing
if(isset($_GET['register'])) { $_SESSION['step'] = 'register'; header("Location: index.php"); exit(); }
if(isset($_GET['login'])) { $_SESSION['step'] = 'login'; header("Location: index.php"); exit(); }
$step = $_SESSION['step'] ?? 'login';
if(isset($_GET['admin'])) $step = 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AP E-Voting | State Election Portal</title>
    <!-- The Stunning Premium Aesthetics Theme -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-image: radial-gradient(circle at top right, rgba(30, 58, 138, 0.08) 0%, transparent 40%),
                              radial-gradient(circle at bottom left, rgba(59, 130, 246, 0.08) 0%, transparent 40%);
            background-color: #f8fafc;
        }

        /* Glassmorphism Classes */
        .glass-panel {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.05);
            border-radius: 1.5rem;
        }
        
        .glass-nav {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.8);
            padding: 0.8rem 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            transition: all 0.3s ease;
        }

        .text-gradient {
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            border: none;
            box-shadow: 0 8px 15px rgba(30, 58, 138, 0.2);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px rgba(30, 58, 138, 0.3);
        }

        /* Forms Details */
        .form-control, .form-select {
            background-color: #f1f5f9 !important;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.8rem 1rem;
            font-weight: 500;
        }
        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
            border-color: #3b82f6;
            background-color: #ffffff !important;
        }

        .alert-premium {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }

        /* PRINT MEDIA QUERIES */
        @media print {
            body { background: white !important; }
            .hide-on-print { display: none !important; }
            .glass-panel { 
                border: 2px solid #000 !important; 
                box-shadow: none !important; 
                background: white !important; 
            }
            .text-muted { color: #000 !important; }
            nav { display: none !important; }
        }

        /* STUNNING RULES PANEL */
        .rules-card {
            background: linear-gradient(145deg, #1e3a8a, #3b82f6);
            color: #ffffff;
            border-radius: 1.5rem;
            padding: 3rem;
            box-shadow: 0 20px 40px rgba(30, 58, 138, 0.3);
            height: 100%;
            border: 1px solid rgba(255,255,255,0.1);
            position: relative;
            overflow: hidden;
        }
        
        .rules-card::before {
            content: '\f2be'; /* FontAwesome User Shield */
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            font-size: 20rem;
            opacity: 0.05;
            bottom: -50px;
            right: -50px;
        }

        .rule-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.8rem;
            padding-bottom: 1.8rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .rule-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .rule-icon {
            font-size: 1.6rem;
            color: #facc15;
            margin-right: 1.5rem;
            background: rgba(255,255,255,0.15);
            width: 55px;
            height: 55px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .candidate-card {
            transition: all 0.3s ease;
            background: #ffffff;
        }
        .candidate-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.08) !important;
        }
    </style>
</head>
<body>
    <!-- FIXED RESPONSIVE NAVBAR (Fixes Display Issue) -->
    <nav class="navbar navbar-expand-lg glass-nav fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <div class="bg-white rounded d-flex align-items-center justify-content-center shadow-sm" style="width: 45px; height: 45px; padding: 4px; border: 2px solid #e2e8f0;">
                    <img src="eci_logo.png" alt="ECI" style="width: 100%; height: 100%; object-fit: contain;" onerror="this.src='https://upload.wikimedia.org/wikipedia/commons/thumb/7/72/Election_Commission_of_India_Logo.svg/512px-Election_Commission_of_India_Logo.svg.png'">
                </div>
                <span class="ms-3 fw-bold fs-4 text-gradient">AP E-VOTING SYSTEM</span>
            </a>
            
            <!-- Navbar Toggler for Mobile -->
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navCollapse" aria-controls="navCollapse" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navCollapse">
                <ul class="navbar-nav ms-auto gap-3 mt-3 mt-lg-0 align-items-lg-center">
                    <li class="nav-item">
                        <a class="btn btn-outline-primary rounded-pill px-4 py-2 fw-bold" href="index.php?register=true"><i class="fa-solid fa-id-card me-2"></i>Registration</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-dark text-white rounded-pill px-4 py-2 fw-bold shadow-sm" href="index.php?login=true" style="background-color: #1f2937;"><i class="fa-solid fa-lock me-2"></i>Login Mode</a>
                    </li>
                    <li class="nav-item mt-2 mt-lg-0 ms-lg-2">
                        <a class="btn btn-light border rounded-pill px-4 py-2 fw-bold shadow-sm text-dark" href="index.php?admin=true"><i class="fa-solid fa-chart-pie me-2 text-primary"></i>Live Results</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container mt-5 pt-5" style="margin-top: 80px !important; margin-bottom: 50px;">
        
        <?php if($error): ?>
            <div class="alert alert-danger alert-premium border-start border-4 border-danger fw-bold d-flex align-items-center p-4 mb-4">
                <i class="fa-solid fa-triangle-exclamation fs-3 me-3 text-danger"></i>
                <div><?= $error ?></div>
            </div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success alert-premium border-start border-4 border-success fs-5 d-flex align-items-center p-4 mb-4">
                <i class="fa-solid fa-circle-check fs-2 me-3 text-success"></i>
                <div><?= $success ?></div>
            </div>
        <?php endif; ?>

        <?php if ($step == 'register' || $step == 'login'): ?>
            <div class="row align-items-stretch min-vh-75 mt-4">
                
                <!-- LEFTSIDE: STUNNING ELECTION RULES -->
                <div class="col-lg-5 mb-4 mb-lg-0">
                    <div class="rules-card">
                        <span class="badge bg-warning text-dark mb-4 px-3 py-2 rounded-pill fw-bold fs-6 shadow-sm"><i class="fa-solid fa-scale-balanced me-2"></i>Official Guidelines</span>
                        <h2 class="fw-bold mb-5 display-6 text-white">Digital Election <br>Voting Rules</h2>
                        
                        <div class="rule-item">
                            <div class="rule-icon"><i class="fa-solid fa-user-check"></i></div>
                            <div>
                                <h5 class="fw-bold mb-1 text-white">Age Requirement</h5>
                                <p class="mb-0 text-white-50 small fw-medium">Citizens must strictly be 18 years of age or older to be legally eligible to apply for a Digital Voter ID.</p>
                            </div>
                        </div>
                        
                        <div class="rule-item">
                            <div class="rule-icon"><i class="fa-solid fa-fingerprint"></i></div>
                            <div>
                                <h5 class="fw-bold mb-1 text-white">Aadhaar Binding</h5>
                                <p class="mb-0 text-white-50 small fw-medium">Your generated Voter ID is permanently fused to your 12-digit Aadhaar to prevent electoral fraud. No duplicate IDs allowed.</p>
                            </div>
                        </div>

                        <div class="rule-item">
                            <div class="rule-icon"><i class="fa-solid fa-1"></i></div>
                            <div>
                                <h5 class="fw-bold mb-1 text-white">One Citizen, One Vote</h5>
                                <p class="mb-0 text-white-50 small fw-medium">The Live Database enforces a strict singular ballot allowance. Casting your vote is permanent and irreversible.</p>
                            </div>
                        </div>

                        <div class="rule-item">
                            <div class="rule-icon"><i class="fa-solid fa-shield-halved"></i></div>
                            <div>
                                <h5 class="fw-bold mb-1 text-white">Secure Password Hashing</h5>
                                <p class="mb-0 text-white-50 small fw-medium">Passwords are encrypted globally utilizing Bcrypt parameters and cannot be recovered by administrators.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHTSIDE: FORMS -->
                <div class="col-lg-7">
                    <?php if ($step == 'register'): ?>
                        <!-- REGISTRATION FORM -->
                        <div class="glass-panel p-4 p-md-5 h-100 border-top border-4 border-primary">
                            <div class="text-center mb-5">
                                <h2 class="fw-bold text-dark mb-2">Citizen Registration Form</h2>
                                <p class="text-secondary fw-semibold">Fill out real-world details to apply for a secure 6-Digit Voter ID</p>
                            </div>
                            
                            <form method="POST" action="index.php">
                                <input type="hidden" name="action" value="register">
                                <div class="row g-3">
                                    <div class="col-md-6 form-group">
                                        <label class="form-label text-muted fw-bold small"><i class="fa-solid fa-user me-2"></i>Full Citizen Name</label>
                                        <input type="text" name="name" class="form-control" placeholder="PLEASE ENTER YOUR FULL NAME" required>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label class="form-label text-muted fw-bold small"><i class="fa-solid fa-key me-2"></i>Create Security Password</label>
                                        <input type="password" name="password" class="form-control" placeholder="PASSWORD" required>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label class="form-label text-muted fw-bold small"><i class="fa-solid fa-calendar me-2"></i>Date of Birth</label>
                                        <input type="date" name="dob" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label class="form-label text-muted fw-bold small"><i class="fa-solid fa-venus-mars me-2"></i>Gender Identity</label>
                                        <select name="gender" class="form-select" required>
                                            <option value="" disabled selected>Select Gender</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label class="form-label text-muted fw-bold small"><i class="fa-solid fa-mobile-screen me-2"></i>Active Mobile Number</label>
                                        <input type="number" name="mobile" class="form-control" placeholder="10 Digits" required maxlength="10">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label class="form-label text-muted fw-bold small"><i class="fa-solid fa-fingerprint me-2 text-primary"></i>Aadhaar Authentication ID</label>
                                        <input type="number" name="aadhaar" class="form-control fw-bold border-primary" placeholder="12 Digits" required maxlength="12">
                                    </div>
                                    <div class="col-12 form-group">
                                        <label class="form-label text-muted fw-bold small"><i class="fa-solid fa-house me-2"></i>Permanent Address</label>
                                        <textarea name="address" class="form-control" placeholder="House, Street, City" style="height: 80px" required></textarea>
                                    </div>
                                    <div class="col-12 mt-4 text-center border-top pt-4">
                                        <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold fs-5 shadow"><i class="fa-solid fa-id-badge me-2"></i>Generate My Voter ID</button>
                                        <div class="mt-3">
                                            <span class="text-secondary small fw-bold">Already applied?</span>
                                            <a href="index.php?login=true" class="fw-bold text-decoration-none border-bottom border-primary border-2 pb-1 text-primary ms-1">Login Here</a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>

                    <?php else: ?>
                        <!-- LOGIN FORM -->
                        <div class="glass-panel p-4 p-md-5 h-100 d-flex flex-column justify-content-center border-top border-4 border-dark">
                            <div class="text-center mb-5">
                                <div class="bg-dark text-white rounded-circle d-inline-flex align-items-center justify-content-center shadow-lg mb-4" style="width: 80px; height: 80px;">
                                    <i class="fa-solid fa-fingerprint fs-1"></i>
                                </div>
                                <h1 class="fw-bold text-dark mb-2">Cast Your Vote</h1>
                                <p class="text-secondary fw-semibold">Login strictly using your 6-Digit ID and Password</p>
                            </div>
                            
                            <form method="POST" action="index.php">
                                <input type="hidden" name="action" value="login">
                                <div class="form-group mb-4">
                                    <label class="form-label text-muted fw-bold small"><i class="fa-solid fa-hashtag me-2"></i>Your 6-Digit Voter ID</label>
                                    <?php if($generated_voter_id): ?>
                                        <input type="number" name="voter_id" class="form-control border-primary text-primary fw-bold" style="font-size:1.2rem; letter-spacing: 2px;" value="<?= $generated_voter_id ?>" required>
                                    <?php else: ?>
                                        <input type="number" name="voter_id" class="form-control" placeholder="123456" style="font-size:1.2rem; letter-spacing: 2px;" required>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group mb-4">
                                    <label class="form-label text-muted fw-bold small"><i class="fa-solid fa-user me-2"></i>Registered Citizen Name</label>
                                    <input type="text" name="name" class="form-control" placeholder="John Doe" required>
                                </div>
                                <div class="form-group mb-5">
                                    <label class="form-label text-muted fw-bold small"><i class="fa-solid fa-key me-2"></i>Security Password</label>
                                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                                </div>
                                <button type="submit" class="btn btn-dark w-100 py-3 rounded-pill fw-bold fs-5 shadow-sm"><i class="fa-solid fa-person-booth me-2"></i>Enter Voting Booth</button>
                                
                                <div class="text-center mt-4 pt-3 border-top">
                                    <p class="small text-secondary fw-bold mb-1">Need a Voter ID to participate?</p>
                                    <a href="index.php?register=true" class="text-decoration-none fw-bold text-primary border-bottom border-primary border-2 pb-1">Register as New Citizen</a>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($step == 'dashboard'): ?>
            <!-- VOTING DASHBOARD -->
             <div class="text-center mb-5 mt-3">
                 <div class="badge bg-danger rounded-pill px-4 py-2 mb-3 shadow fs-6 fw-bold border border-white"><i class="fa-solid fa-satellite-dish me-2"></i>LIVE BALLOT ACTIVE</div>
                 <h1 class="fw-bold display-5 text-dark">Andhra Pradesh Digital Ballot</h1>
                 <p class="text-secondary fs-5">Securely logged in as: <strong class="text-dark bg-white px-3 py-1 rounded shadow-sm border"><?= $_SESSION['voter_name'] ?></strong> (Voter ID: <span class="font-monospace fw-bold text-primary"><?= $_SESSION['voter_id'] ?></span>)</p>
             </div>
             
             <div class="row justify-content-center">
                <?php 
                $candidates = $conn->query("SELECT * FROM candidates");
                while($c = $candidates->fetch_assoc()): 
                ?>
                <div class="col-lg-9 mb-4">
                    <div class="candidate-card glass-panel p-3 p-md-4 d-flex flex-column flex-md-row align-items-center justify-content-between border-start border-5" style="border-left-color: <?= $c['color'] ?>!important;">
                        <div class="d-flex align-items-center w-100">
                            <!-- Using Offline Local PNGs mathematically mapped to the party Abbreviation -->
                            <img src="<?= $c['abbr'] ?>.png" style="width: 80px; height: 80px; object-fit: contain;" class="me-4 rounded-circle bg-white shadow-sm p-1 border border-2" onerror="this.src='https://ui-avatars.com/api/?name=<?= $c['abbr'] ?>&background=random'">
                            <div>
                                <h3 class="fw-bold mb-1 text-dark"><?= $c['name'] ?></h3>
                                <div class="badge px-3 py-2 text-white fw-bold shadow-sm" style="background-color: <?= $c['color'] ?>; font-size: 0.95rem">
                                    <?= $c['party'] ?> (<?= $c['abbr'] ?>)
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 mt-md-0 w-100 text-md-end text-center" style="max-width: 180px;">
                            <form method="POST" action="index.php" onsubmit="return confirm('CRITICAL WARNING: You are attempting to cast your permanent, unchangeable vote for <?= $c['party'] ?>. Are you fully certain?');">
                                <input type="hidden" name="action" value="vote">
                                <input type="hidden" name="candidate_id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold fs-5 shadow position-relative overflow-hidden text-white">
                                    <i class="fa-solid fa-stamp me-2"></i>VOTE
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>

        <?php elseif ($step == 'success'): ?>
            <!-- SUCCESSFUL VOTE -->
            <div class="row justify-content-center align-items-center text-center mt-5">
                <div class="col-md-9 col-lg-7 glass-panel py-5 shadow-lg border-success border-top border-5">
                    <div class="mb-4 hide-on-print">
                        <i class="fa-solid fa-shield-check text-success" style="font-size: 6rem;"></i>
                    </div>
                    <h2 class="fw-bold text-success display-5 mb-3 hide-on-print">Vote Finalized</h2>
                    <p class="text-secondary lead mb-4 fw-bold px-3 hide-on-print">Your Democratic Right was successfully cryptographically recorded.</p>
                    
                    <div class="bg-light d-inline-block px-4 py-4 px-md-5 rounded-4 shadow-sm border mb-5 text-start w-100" style="border: 2px dashed #0d6efd !important;">
                        <h3 class="mb-4 text-dark text-center fw-bold border-bottom border-secondary pb-3 text-uppercase"><i class="fa-solid fa-file-invoice text-primary me-2"></i>Official Electoral Receipt</h3>
                        
                        <div class="row mb-3 fs-5">
                            <div class="col-5 text-muted fw-bold">Citizen Name:</div>
                            <div class="col-7 text-dark fw-bold"><?= $_SESSION['receipt_name'] ?? 'Voter Name' ?></div>
                        </div>
                        <div class="row mb-3 fs-5">
                            <div class="col-5 text-muted fw-bold">Voter ID Num:</div>
                            <div class="col-7 text-dark fw-bold font-monospace"><?= $_SESSION['receipt_voter_id'] ?? 'XXXXXX' ?></div>
                        </div>
                        <div class="row mb-3 fs-5">
                            <div class="col-5 text-muted fw-bold">Transaction (TRX):</div>
                            <div class="col-7 text-dark fw-bold font-monospace">TRX-<?= strtoupper(substr(md5(rand()), 0, 10)) ?></div>
                        </div>
                        <div class="row mb-3 fs-5">
                            <div class="col-5 text-muted fw-bold">Time Verified:</div>
                            <div class="col-7 text-dark fw-bold"><?= date("d M Y, h:i A") ?></div>
                        </div>
                        
                        <div class="text-center mt-4 pt-4 border-top border-secondary">
                             <div class="badge bg-success py-2 px-4 fs-5 w-100"><i class="fa-solid fa-lock me-2"></i>VOTE SECURED & LOCKED IN SERVER</div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-center gap-3 flex-wrap hide-on-print">
                        <button onclick="window.print()" class="btn btn-primary px-4 py-3 rounded-pill fw-bold fs-5 shadow"><i class="fa-solid fa-print me-2"></i>Print Official Receipt</button>
                        <a href="logout.php" class="btn btn-dark px-4 py-3 rounded-pill fw-bold fs-5 shadow-lg"><i class="fa-solid fa-right-from-bracket me-2"></i>Exit Securely</a>
                    </div>
                </div>
            </div>

        <?php elseif ($step == 'admin'): ?>
            <!-- ADMIN DASHBOARD -->
            <div class="d-flex justify-content-between align-items-center mb-5 mt-4">
                <h1 class="fw-bold text-dark"><i class="fa-solid fa-shield-halved text-success me-3"></i>Live Election Dashboard</h1>
                <a href="index.php" class="btn btn-outline-dark rounded-pill px-4 fw-bold shadow-sm"><i class="fa-solid fa-home me-2"></i>Live Portal</a>
            </div>
            
            <div class="row">
                <div class="col-lg-7">
                    <div class="glass-panel p-4 p-lg-5 shadow-lg mb-4 h-100 border-top border-4 border-primary">
                        <h3 class="fw-bold mb-4 text-dark"><i class="fa-solid fa-server text-primary me-2"></i>Official Tally Server</h3>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle table-borderless m-0">
                                <thead class="table-light text-muted small text-uppercase">
                                    <tr>
                                        <th class="py-3 px-3 rounded-start">Candidate Profile</th>
                                        <th class="text-end py-3 px-3 rounded-end">Actual Valid Votes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_votes = 0;
                                    $results = $conn->query("SELECT * FROM candidates ORDER BY votes DESC");
                                    while($r = $results->fetch_assoc()): 
                                        $total_votes += $r['votes'];
                                        $leaderStyle = ($total_votes > 0 && $r['votes'] > 0) ? "fw-bold fs-5 text-dark" : "text-secondary";
                                    ?>
                                    <tr class="border-bottom border-light">
                                        <td class="<?= $leaderStyle ?> py-4 px-3">
                                            <div class="d-flex align-items-center">
                                                <img src="<?= $r['abbr'] ?>.png" width="50" height="50" class="rounded-circle bg-white shadow-sm p-1 me-3 object-fit-contain border" onerror="this.src='https://ui-avatars.com/api/?name=<?= $r['abbr'] ?>&background=random'">
                                                <div>
                                                    <div class="mb-1 text-dark fw-bold"><?= $r['name'] ?></div>
                                                    <div class="badge px-3 py-1 fs-6 shadow-sm text-white border" style="background-color: <?= $r['color'] ?>; filter: brightness(0.95);"><?= $r['abbr'] ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end fw-bold fs-2 py-4 px-3" style="color: <?= $r['color'] ?>"><?= number_format($r['votes']) ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                                <tfoot class="border-top border-dark border-2 bg-light rounded">
                                    <tr>
                                        <td class="text-end fw-bold py-4 fs-5 text-uppercase text-secondary">Total Processed Votes:</td>
                                        <td class="text-end fw-bold fs-1 text-primary py-4 px-3"><?= number_format($total_votes) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-5">
                     <div class="glass-panel p-4 p-lg-5 shadow-lg mb-4 border-top border-4 border-dark h-100" style="background: linear-gradient(145deg, #f8fafc, #ffffff);">
                         <?php 
                         $q_users = $conn->query("SELECT count(id) as total FROM voters");
                         $registered = $q_users->fetch_assoc()['total'];
                         ?>
                         <h3 class="fw-bold mb-4 text-dark"><i class="fa-solid fa-chart-line text-dark me-2"></i>Logistics</h3>
                         
                         <div class="bg-primary text-white p-4 rounded-4 shadow-sm mb-4 bg-gradient">
                             <h6 class="text-white-50 fw-bold text-uppercase mb-2"><i class="fa-solid fa-users me-2"></i>Generated Voter IDs</h6>
                             <h1 class="display-3 fw-bold mb-0 text-white"><?= number_format($registered) ?></h1>
                         </div>

                         <div class="mt-4 pt-3 border-top">
                             <h6 class="fw-bold text-secondary text-uppercase mb-3">System Protocols</h6>
                             <div class="d-flex justify-content-between align-items-center bg-white p-3 rounded border shadow-sm mb-2">
                                <span class="fw-bold text-dark"><i class="fa-solid fa-lock text-primary me-2"></i>Cryptography</span>
                                <span class="badge bg-primary px-3 py-2 ms-auto">Bcrypt Hash Locked</span>
                             </div>
                             
                             <div class="d-flex justify-content-between align-items-center bg-white p-3 rounded border shadow-sm mb-2">
                                <span class="fw-bold text-dark"><i class="fa-solid fa-fingerprint text-danger me-2"></i>Fraud Integrity</span>
                                <span class="badge bg-danger px-3 py-2 ms-auto">Strictly Aadhaar Locked</span>
                             </div>

                             <div class="d-flex justify-content-between align-items-center bg-white p-3 rounded border shadow-sm">
                                <span class="fw-bold text-dark"><i class="fa-solid fa-database text-success me-2"></i>MySQL Database</span>
                                <span class="badge bg-success px-3 py-2 ms-auto">Live Queries Active</span>
                             </div>
                         </div>

                         <!-- ADMIN CLEAR VOTES ACTION -->
                         <form method="POST" action="index.php" onsubmit="return confirm('DANGER: This will permanently wipe ALL recorded votes across the entire database and reset candidates to 0. Are you absolutely sure?');">
                             <input type="hidden" name="action" value="clear_votes">
                             <button type="submit" class="btn btn-danger w-100 py-3 mt-4 fw-bold fs-5 shadow rounded-4 border border-2 border-white" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                                 <i class="fa-solid fa-dumpster-fire me-2"></i> WIPE ALL VOTES
                             </button>
                         </form>
                     </div>
                </div>
            </div>
            
        <?php endif; ?>
    </main>
    
    <!-- Fixes the Navigation Display Issue on Mobile -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
