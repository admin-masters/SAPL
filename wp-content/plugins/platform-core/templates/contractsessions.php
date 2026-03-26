<?php
// Contracts & Sessions Page
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Contracts & Sessions</title>

<style>
*{margin:0;padding:0;box-sizing:border-box}
body{
    font-family:Inter, system-ui, sans-serif;
    background:#f8fafc;
    color:#0f172a;
}

/* ===== MAIN ===== */
.main{
    max-width:1400px;
    margin:0 auto;
    padding:24px 32px;
}

/* HEADER */
.top{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}
.top h2{
    font-size:20px;
}
.profile img{
    width:36px;
    height:36px;
    border-radius:50%;
}

/* BANNER */
.banner{
    background:#6d6df5;
    color:#ffffff;
    border-radius:14px;
    padding:20px;
    margin-bottom:24px;
}
.banner h3{
    font-size:18px;
}
.banner small{
    display:block;
    margin-top:6px;
    font-size:13px;
    opacity:.9;
}

/* GRID */
.grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:24px;
}

/* CARD */
.card{
    background:#ffffff;
    border:1px solid #e5e7eb;
    border-radius:16px;
    padding:18px;
}
.card h4{
    margin-bottom:14px;
}

/* ITEM */
.item{
    border-bottom:1px solid #e5e7eb;
    padding:12px 0;
}
.item:last-child{
    border-bottom:none;
}
.item-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.item strong{
    font-size:14px;
}
.item small{
    display:block;
    font-size:12px;
    color:#64748b;
    margin-top:4px;
}

/* BADGES */
.badge{
    font-size:11px;
    padding:4px 10px;
    border-radius:20px;
}
.active{
    background:#dcfce7;
    color:#166534;
}
.pending{
    background:#fef3c7;
    color:#92400e;
}

/* ACTIONS */
.actions{
    margin-top:8px;
}
.actions button{
    padding:6px 12px;
    font-size:12px;
    border-radius:8px;
    border:1px solid #e5e7eb;
    margin-right:6px;
    cursor:pointer;
}
.accept{
    background:#000;
    color:#fff;
    border:none;
}
.decline{
    background:#fff;
}
</style>
</head>

<body>

<!-- MAIN (no sidebar wrapper, full width with max-width container) -->
<div class="main">

    <div class="top">
        <h2>Contracts & Sessions</h2>
        <div class="profile">
            <img src="assets/images/doctor2.avif">
        </div>
    </div>

    <div class="banner">
        <h3>Your Educational Contracts</h3>
        <small>You have 3 active contracts and 2 pending session requests</small>
    </div>

    <div class="grid">

        <!-- ACTIVE CONTRACTS -->
        <div class="card">
            <h4>Active Contracts</h4>

            <div class="item">
                <div class="item-head">
                    <strong>Medical Training Contract</strong>
                    <span class="badge active">Active</span>
                </div>
                <small>Duration: 6 months</small>
                <small>Value: $5,000</small>
            </div>

            <div class="item">
                <div class="item-head">
                    <strong>Pediatric Specialty Course</strong>
                    <span class="badge active">Active</span>
                </div>
                <small>Duration: 3 months</small>
                <small>Value: $2,500</small>
            </div>

            <div class="item">
                <div class="item-head">
                    <strong>Emergency Care Training</strong>
                    <span class="badge active">Active</span>
                </div>
                <small>Duration: 4 months</small>
                <small>Value: $3,200</small>
            </div>
        </div>

        <!-- REQUESTED SESSIONS -->
        <div class="card">
            <h4>Requested Sessions</h4>

            <div class="item">
                <div class="item-head">
                    <strong>Cardiology Workshop</strong>
                    <span class="badge pending">Pending</span>
                </div>
                <small>Requested by: Dr. John Smith</small>
                <small>Date: Next Week</small>
                <div class="actions">
                    <button class="accept">Accept</button>
                    <button class="decline">Decline</button>
                </div>
            </div>

            <div class="item">
                <div class="item-head">
                    <strong>Surgical Training</strong>
                    <span class="badge pending">Pending</span>
                </div>
                <small>Requested by: Dr. Sarah Johnson</small>
                <small>Date: Next Month</small>
                <div class="actions">
                    <button class="accept">Accept</button>
                    <button class="decline">Decline</button>
                </div>
            </div>

        </div>

    </div>

</div>

</body>
</html>