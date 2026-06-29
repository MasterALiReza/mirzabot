<?php
session_start();
if (isset($_SESSION['agent_id'])) {
    header("Location: agent_users.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود نماینده - OxBot</title>
    <!-- Fonts -->
    <link href="fonts/vazirmatn/Vazirmatn-font-face.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #6c5ce7;
            --primary-dark: #5b4bc4;
            --bg-color: #0f111a;
            --panel-bg: rgba(25, 28, 41, 0.7);
            --text-main: #f1f2f6;
            --text-muted: #a4b0be;
            --border-color: rgba(255, 255, 255, 0.1);
            --glass-bg: rgba(25, 28, 41, 0.6);
            --glass-border: rgba(255, 255, 255, 0.05);
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
            --success: #00b894;
            --danger: #ff7675;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(108, 92, 231, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 85% 30%, rgba(0, 184, 148, 0.15) 0%, transparent 50%);
            background-attachment: fixed;
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
            position: relative;
            z-index: 10;
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 40px 30px;
            box-shadow: var(--glass-shadow);
            text-align: center;
            animation: slideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUp {
            0% { opacity: 0; transform: translateY(30px) scale(0.95); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: white;
            margin: 0 auto 24px;
            box-shadow: 0 10px 20px rgba(108, 92, 231, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(108, 92, 231, 0.4); }
            70% { box-shadow: 0 0 0 15px rgba(108, 92, 231, 0); }
            100% { box-shadow: 0 0 0 0 rgba(108, 92, 231, 0); }
        }

        .login-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            background: linear-gradient(to right, #fff, var(--text-muted));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .login-subtitle {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 30px;
        }

        .custom-input-group {
            position: relative;
            margin-bottom: 24px;
            text-align: right;
        }

        .custom-input {
            width: 100%;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 16px 45px 16px 16px;
            color: white;
            font-family: inherit;
            font-size: 15px;
            transition: all 0.3s ease;
            text-align: left;
            direction: ltr;
        }

        .custom-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(108, 92, 231, 0.1);
            background: rgba(0, 0, 0, 0.4);
        }

        .custom-input-group i {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            right: 18px;
            color: var(--text-muted);
            transition: color 0.3s ease;
        }

        .custom-input:focus + i {
            color: var(--primary);
        }

        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            border-radius: 16px;
            padding: 16px;
            color: white;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(108, 92, 231, 0.4);
        }

        .btn-login:active {
            transform: translateY(1px);
        }

        /* Form states */
        #step-otp {
            display: none;
        }

        .alert-toast {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(-100px);
            background: var(--danger);
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 500;
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 9999;
            box-shadow: 0 4px 15px rgba(255, 118, 117, 0.3);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-toast.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }
        
        .alert-toast.success {
            background: var(--success);
            box-shadow: 0 4px 15px rgba(0, 184, 148, 0.3);
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            display: none;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .btn-login.loading span {
            display: none;
        }

        .btn-login.loading .spinner {
            display: block;
        }

        .timer-text {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 15px;
            display: block;
        }
        
        .back-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 13px;
            cursor: pointer;
            margin-top: 15px;
            transition: color 0.3s ease;
        }
        
        .back-btn:hover {
            color: white;
        }

    </style>
</head>
<body>

    <div class="alert-toast" id="alertToast">
        <i class="fa-solid fa-circle-exclamation"></i>
        <span id="alertMsg">خطایی رخ داد!</span>
    </div>

    <div class="login-container">
        <!-- Step 1: Telegram ID -->
        <div class="glass-card" id="step-id">
            <div class="logo-icon">
                <i class="fa-solid fa-user-tie"></i>
            </div>
            <h1 class="login-title">ورود نماینده</h1>
            <p class="login-subtitle">جهت ورود، آیدی عددی تلگرام خود را وارد کنید</p>

            <form id="form-id" onsubmit="event.preventDefault(); sendOTP();">
                <div class="custom-input-group">
                    <input type="number" class="custom-input" id="telegramId" placeholder="مانند: 123456789" required autocomplete="off">
                    <i class="fa-brands fa-telegram"></i>
                </div>
                
                <button type="submit" class="btn-login" id="btnSendCode">
                    <span>ارسال کد تایید</span>
                    <div class="spinner"></div>
                </button>
            </form>
        </div>

        <!-- Step 2: OTP Code -->
        <div class="glass-card" id="step-otp">
            <div class="logo-icon" style="background: linear-gradient(135deg, var(--success), #00d2a6);">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h1 class="login-title">تایید هویت</h1>
            <p class="login-subtitle">کد 5 رقمی ارسال شده به ربات را وارد کنید</p>

            <form id="form-otp" onsubmit="event.preventDefault(); verifyOTP();">
                <div class="custom-input-group">
                    <input type="number" class="custom-input" id="otpCode" placeholder="* * * * *" required autocomplete="off" style="text-align: center; font-size: 20px; letter-spacing: 5px;">
                    <i class="fa-solid fa-key" style="right: auto; left: 18px;"></i>
                </div>
                
                <button type="submit" class="btn-login" id="btnVerifyCode" style="background: linear-gradient(135deg, var(--success), #00d2a6);">
                    <span>ورود به پنل</span>
                    <div class="spinner"></div>
                </button>
                
                <span class="timer-text" id="otpTimer">اعتبار کد: 02:00</span>
                <button type="button" class="back-btn" onclick="goBack()">تغییر آیدی تلگرام</button>
            </form>
        </div>
    </div>

    <script>
        let timerInterval;

        function showAlert(msg, isSuccess = false) {
            const toast = document.getElementById('alertToast');
            const alertMsg = document.getElementById('alertMsg');
            const icon = toast.querySelector('i');
            
            alertMsg.textContent = msg;
            
            if (isSuccess) {
                toast.classList.add('success');
                icon.className = 'fa-solid fa-circle-check';
            } else {
                toast.classList.remove('success');
                icon.className = 'fa-solid fa-circle-exclamation';
            }
            
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        async function sendOTP() {
            const telegramId = document.getElementById('telegramId').value.trim();
            if (!telegramId) return showAlert('لطفا آیدی تلگرام را وارد کنید');

            const btn = document.getElementById('btnSendCode');
            btn.classList.add('loading');
            btn.disabled = true;

            try {
                const fd = new FormData();
                fd.append('action', 'send_otp');
                fd.append('telegram_id', telegramId);

                const res = await fetch('ajax/agent_auth.php', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.status === 'success') {
                    document.getElementById('step-id').style.display = 'none';
                    document.getElementById('step-otp').style.display = 'block';
                    document.getElementById('otpCode').focus();
                    startTimer(120);
                } else {
                    showAlert(data.message || 'خطا در ارسال کد');
                }
            } catch (err) {
                showAlert('خطای ارتباط با سرور');
            } finally {
                btn.classList.remove('loading');
                btn.disabled = false;
            }
        }

        async function verifyOTP() {
            const otpCode = document.getElementById('otpCode').value.trim();
            if (!otpCode) return showAlert('لطفا کد تایید را وارد کنید');

            const btn = document.getElementById('btnVerifyCode');
            btn.classList.add('loading');
            btn.disabled = true;

            try {
                const fd = new FormData();
                fd.append('action', 'verify_otp');
                fd.append('otp_code', otpCode);

                const res = await fetch('ajax/agent_auth.php', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.status === 'success') {
                    showAlert('ورود موفقیت آمیز...', true);
                    setTimeout(() => {
                        window.location.href = 'agent_users.php';
                    }, 1000);
                } else {
                    showAlert(data.message || 'کد وارد شده اشتباه است');
                    btn.classList.remove('loading');
                    btn.disabled = false;
                }
            } catch (err) {
                showAlert('خطای ارتباط با سرور');
                btn.classList.remove('loading');
                btn.disabled = false;
            }
        }

        function startTimer(duration) {
            clearInterval(timerInterval);
            let timer = duration;
            const display = document.getElementById('otpTimer');
            
            timerInterval = setInterval(function () {
                let minutes = parseInt(timer / 60, 10);
                let seconds = parseInt(timer % 60, 10);

                minutes = minutes < 10 ? "0" + minutes : minutes;
                seconds = seconds < 10 ? "0" + seconds : seconds;

                display.textContent = "اعتبار کد: " + minutes + ":" + seconds;

                if (--timer < 0) {
                    clearInterval(timerInterval);
                    display.textContent = "کد منقضی شد! لطفا مجدد درخواست دهید.";
                    document.getElementById('btnVerifyCode').disabled = true;
                }
            }, 1000);
        }

        function goBack() {
            clearInterval(timerInterval);
            document.getElementById('step-otp').style.display = 'none';
            document.getElementById('step-id').style.display = 'block';
            document.getElementById('otpCode').value = '';
            document.getElementById('btnVerifyCode').disabled = false;
        }
    </script>
</body>
</html>
