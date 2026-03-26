<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Certificate of Completion</title>

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

/* ===== VERIFY BOX ===== */
.verify-box{
    border:1px solid #e5e7eb;
    border-radius:8px;
    padding:10px 12px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    font-size:11px;
    color:#475569;
}

.verify-box a{
    color:#0f172a;
    font-weight:600;
    text-decoration:none;
    font-size:11px;
}

.verify-box a:hover{
    text-decoration:underline;
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
    .verify-box{padding:8px 10px}
    .footer{
        margin-top:16px;
        padding:8px 0;
        font-size:9px;
    }
    .actions{display:none}
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

        <h1>Certificate of Completion</h1>
        <div class="subtitle">
            Certificate #{{CERTIFICATE_ID}} &mdash; Issued to {{STUDENT_NAME}} upon successful completion of a webinar.
        </div>

        <!-- Recipient -->
        <div class="section">
            <h3>Recipient Details</h3>
            <div class="info-group">
                <div class="info-row">
                    <span>Name</span>
                    <strong>{{STUDENT_NAME}}</strong>
                </div>
                <div class="info-row">
                    <span>Email</span>
                    <strong>{{STUDENT_EMAIL}}</strong>
                </div>
            </div>
        </div>

        <!-- Webinar -->
        <div class="section">
            <h3>Webinar Details</h3>
            <div class="info-group">
                <div class="info-row">
                    <span>Webinar Title</span>
                    <strong>{{WEBINAR_TITLE}}</strong>
                </div>
                <div class="info-row">
                    <span>Instructor</span>
                    <strong>{{INSTRUCTOR_NAME}}</strong>
                </div>
                <div class="info-row">
                    <span>Date of Completion</span>
                    <strong>{{COMPLETION_DATE}}</strong>
                </div>
                <div class="info-row">
                    <span>Duration</span>
                    <strong>{{DURATION}}</strong>
                </div>
            </div>
        </div>

        <!-- Issued By -->
        <div class="section">
            <h3>Issued By</h3>
            <div class="info-group">
                <div class="info-row">
                    <span>Program Director</span>
                    <strong>{{DIRECTOR_NAME}}</strong>
                </div>
                <div class="info-row">
                    <span>Issue Date</span>
                    <strong>{{ISSUE_DATE}}</strong>
                </div>
            </div>
        </div>

        <!-- Verify -->
        <div class="section">
            <h3>Verification</h3>
            <div class="verify-box">
                <span>Verify this certificate online</span>
                <a href="{{VERIFY_URL}}" target="_blank">{{VERIFY_URL}}</a>
            </div>
        </div>

        <!-- Actions -->
        <div class="actions">
            <button class="download" onclick="window.print()">Download Certificate</button>
        </div>

    </div>
</div>

<div class="footer">
    <p>{{COMPANY_ADDRESS}}</p>
    <p>Generated by {{COMPANY_NAME}}</p>
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