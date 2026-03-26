<?php
// Check user login status
$is_logged_in = is_user_logged_in();
$user_name = '';
$login_link = wp_login_url(get_permalink());
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Contract Agreement</title>

<style>
*{box-sizing:border-box;margin:0;padding:0}

body{
    font-family:Inter,system-ui,-apple-system,Segoe UI,sans-serif;
    background:#f7f9fc;
    color:#0f172a;
    font-size:12px;
}

/* ===== HEADER ===== */
.header{
    background:#ffffff;
    border-bottom:1px solid #e5e7eb;
}
.header-inner{
    max-width:900px;
    margin:auto;
    padding:10px 20px;
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.logo{
    font-weight:600;
    font-size:15px;
}
.signin{
    padding:6px 12px;
    background:#0f172a;
    color:#fff;
    border:none;
    border-radius:6px;
    font-size:12px;
    text-decoration:none;
}
.user-info{
    font-size:12px;
    font-weight:600;
}

/* ===== PAGE ===== */
.page{
    max-width:900px;
    margin:16px auto;
    padding:0 20px;
}

.card{
    background:#ffffff;
    border:1px solid #e5e7eb;
    border-radius:10px;
    padding:16px 20px;
}

/* ===== TITLES ===== */
h1{
    font-size:18px;
    margin-bottom:3px;
    font-weight:600;
}
.subtitle{
    font-size:11px;
    color:#64748b;
    margin-bottom:14px;
}

.section{
    margin-bottom:14px;
}

.section h3{
    font-size:13px;
    margin-bottom:8px;
    font-weight:600;
}

/* ===== ROW SYSTEM ===== */
.info-group{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:8px 24px;
}

.info-row{
    display:flex;
    flex-direction:column;
    gap:2px;
}

.info-row span{
    font-size:10px;
    color:#64748b;
    text-transform:uppercase;
    letter-spacing:0.3px;
}

.info-row strong{
    font-size:12px;
    font-weight:500;
}

/* ===== PAYMENT ===== */
.payment-box{
    border:1px solid #e5e7eb;
    border-radius:8px;
    padding:10px 12px;
}

.payment-row{
    display:flex;
    justify-content:space-between;
    font-size:12px;
}

.payment-row.total{
    font-weight:600;
}

/* ===== TERMS ===== */
.terms{
    font-size:11px;
    color:#475569;
    line-height:1.5;
}

/* ===== ACTIONS ===== */
.actions{
    margin-top:16px;
    display:flex;
    justify-content:flex-end;
    gap:10px;
}

.actions button{
    padding:8px 14px;
    border-radius:6px;
    font-size:12px;
    cursor:pointer;
}

.download{
    background:#0f172a;
    color:#ffffff;
    border:none;
}

/* ===== FOOTER ===== */
.footer{
    margin-top:20px;
    padding:12px 0;
    text-align:center;
    font-size:10px;
    color:#64748b;
    border-top:1px solid #e5e7eb;
    background:#ffffff;
}
.footer p{margin:2px 0}

/* ===== PRINT STYLES ===== */
@media print{
    @page{
        margin:0.5in;
    }
    body{
        background:#fff;
        font-size:11px;
    }
    .page{
        margin:0;
        padding:0;
    }
    .card{
        border:none;
        padding:0;
    }
    .header-inner{
        padding:8px 0;
    }
    h1{font-size:16px}
    .subtitle{font-size:10px;margin-bottom:12px}
    .section{margin-bottom:12px}
    .section h3{font-size:12px;margin-bottom:6px}
    .info-group{gap:6px 20px}
    .info-row span{font-size:9px}
    .info-row strong{font-size:11px}
    .payment-box{padding:8px 10px}
    .terms{font-size:10px;line-height:1.4}
    .footer{
        margin-top:16px;
        padding:8px 0;
        font-size:9px;
    }
    .actions{margin-top:12px}
}
</style>
</head>

<body>

<!-- HEADER -->
<div class="header">
    <div class="header-inner">
        <div class="logo">{{COMPANY_NAME}}</div>
    </div>
</div>

<div class="page">

    <div class="card">

        <h1>Contract Agreement</h1>
        <div class="subtitle">
            Contract #{{CONTRACT_ID}} - Please review the session details and confirm to proceed with payment.
        </div>

        <!-- Educator -->
        <div class="section">
            <h3>Educator Details</h3>
            <div class="info-group">
                <div class="info-row">
                    <span>Name</span>
                    <strong>{{EXPERT_NAME}}</strong>
                </div>
                <div class="info-row">
                    <span>College</span>
                    <strong>{{COLLEGE_NAME}}</strong>
                </div>
                <div class="info-row">
                    <span>Topic</span>
                    <strong>{{TOPIC}}</strong>
                </div>
                <div class="info-row">
                    <span>Session Type</span>
                    <strong>One-on-One Tutoring</strong>
                </div>
            </div>
        </div>

        <!-- Session -->
        <div class="section">
            <h3>Session Details</h3>
            <div class="info-group">
                <div class="info-row">
                    <span>Date/Time</span>
                    <strong>{{START}}</strong>
                </div>
                <div class="info-row">
                    <span>Duration</span>
                    <strong>{{DURATION}}</strong>
                </div>
                <div class="info-row">
                    <span>Mode</span>
                    <strong>Online</strong>
                </div>
            </div>
        </div>

        <!-- Payment -->
        <div class="section">
            <h3>Payment Summary</h3>
            <div class="payment-box">
                <div class="payment-row total">
                    <span>Total Amount</span>
                    <span>{{AMOUNT}}</span>
                </div>
            </div>
        </div>

        <!-- Terms -->
        <div class="section">
            <h3>Terms & Conditions</h3>
            <div class="terms">
                {{TERMS_HTML}}
            </div>
        </div>

        <!-- Actions -->
        <div class="actions">
            <button class="download" onclick="window.print()">Download Contract</button>
        </div>

    </div>
</div>

<div class="footer">
    <p>{{COMPANY_ADDRESS}}</p>
    <p>Generated by platform-core · Flow 7</p>
</div>

<script>
window.onbeforeprint = function() {
    document.querySelector('.header').style.display = 'none';
    document.querySelector('.actions').style.display = 'none';
};

window.onafterprint = function() {
    document.querySelector('.header').style.display = 'block';
    document.querySelector('.actions').style.display = 'flex';
};
</script>

</body>
</html>