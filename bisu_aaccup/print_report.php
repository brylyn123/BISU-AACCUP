<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) { die("Access Denied"); }

$program_id = isset($_GET['program_id']) ? intval($_GET['program_id']) : 0;
if (!$program_id) die("Invalid Program ID");

// Fetch Program Info
$p_res = $conn->query("SELECT p.program_name, p.program_code, c.college_name, c.college_id 
                       FROM programs p 
                       JOIN colleges c ON p.college_id = c.college_id 
                       WHERE p.program_id = $program_id");
$program = $p_res->fetch_assoc();

// Determine Back Link based on Role
$role = strtolower($_SESSION['role'] ?? '');
$back_link = 'role_home.php'; // Default

if (strpos($role, 'admin') !== false) {
    $back_link = 'admin_dashboard.php?view=self_survey';
    if (isset($program['college_id'])) {
        $back_link .= '&college_id=' . $program['college_id'];
    }
} elseif (strpos($role, 'dean') !== false) {
    $back_link = 'dean_dashboard.php?view=self_survey';
} elseif (strpos($role, 'chairperson') !== false) {
    $back_link = 'chairperson_dashboard.php?view=self_survey';
} elseif (strpos($role, 'focal') !== false || strpos($role, 'faculty') !== false) {
    $back_link = 'focal_dashboard.php?view=self_survey';
} elseif (strpos($role, 'accreditor') !== false) {
    $back_link = "accreditor_dashboard.php?view=documents&college_id={$program['college_id']}&program_id=$program_id";
}

// Fetch Ratings (Average per area)
$ratings = [];
$r_sql = "SELECT a.area_id, a.area_no, a.area_title, AVG(r.rating) as avg_rating 
          FROM areas a
          LEFT JOIN survey_ratings r ON a.area_id = r.area_id AND r.program_id = $program_id
          GROUP BY a.area_id
          ORDER BY a.area_no";
$r_res = $conn->query($r_sql);
while($row = $r_res->fetch_assoc()) {
    $ratings[] = $row;
}

// Fetch Qualitative Feedback (Findings)
$findings = [];
$f_sql = "SELECT f.feedback_text, d.file_name, a.area_id, a.area_no, a.area_title, CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) as accreditor, f.created_at
          FROM document_feedback f
          JOIN documents d ON f.doc_id = d.doc_id
          JOIN areas a ON d.area_id = a.area_id
          JOIN users u ON f.user_id = u.user_id
          JOIN cycles c ON d.cycle_id = c.cycle_id
          WHERE c.program_id = $program_id
          ORDER BY a.area_no, f.created_at DESC";
$f_res = $conn->query($f_sql);
while($row = $f_res->fetch_assoc()) {
    $findings[$row['area_id']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accreditation Report - <?= htmlspecialchars($program['program_code']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #1e293b; line-height: 1.6; max-width: 900px; margin: 0 auto; padding: 40px; background: #fff; }
        .header { text-align: center; margin-bottom: 40px; border-bottom: 3px solid #4f46e5; padding-bottom: 20px; }
        .logo-text { font-size: 28px; font-weight: 800; color: #4f46e5; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 1px; }
        .sub-header { font-size: 16px; color: #64748b; font-weight: 500; }
        
        .meta-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 40px; display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .meta-item strong { display: block; font-size: 12px; text-transform: uppercase; color: #64748b; margin-bottom: 2px; }
        .meta-item span { font-size: 16px; font-weight: 600; color: #334155; }

        h2 { font-size: 20px; color: #0f172a; border-left: 5px solid #4f46e5; padding-left: 15px; margin: 40px 0 20px 0; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 14px; }
        th, td { padding: 12px 15px; border-bottom: 1px solid #e2e8f0; text-align: left; }
        th { background-color: #f1f5f9; font-weight: 700; color: #475569; text-transform: uppercase; font-size: 12px; }
        .score-cell { font-weight: bold; text-align: center; width: 100px; }
        .score-high { color: #059669; }
        .score-low { color: #dc2626; }

        .finding-item { margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #f1f5f9; }
        .finding-item:last-child { border-bottom: none; }
        .finding-meta { font-size: 12px; color: #94a3b8; margin-bottom: 5px; display: flex; justify-content: space-between; }
        .finding-text { font-size: 14px; color: #334155; background: #fff; }
        .doc-ref { font-weight: 600; color: #4f46e5; }

        .footer { margin-top: 60px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 20px; }
        
        @media print {
            body { padding: 0; max-width: 100%; }
            .no-print { display: none; }
            .header { border-bottom-color: #000; }
            h2 { border-left-color: #000; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 20px; text-align: right; display: flex; justify-content: flex-end; gap: 10px;">
        <a href="<?= $back_link ?>" style="padding: 10px 20px; background: #64748b; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-decoration: none; display: inline-flex; align-items: center;">
            <i class="fas fa-arrow-left" style="margin-right: 8px;"></i> Back
        </a>
        <button onclick="window.print()" style="padding: 10px 20px; background: #4f46e5; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            <i class="fas fa-print" style="margin-right: 8px;"></i> Print / Save as PDF
        </button>
    </div>

    <div class="header">
        <div class="logo-text">Accreditation Report</div>
        <div class="sub-header">BISU Candijay Campus</div>
    </div>

    <div class="meta-box">
        <div class="meta-item"><strong>Program</strong> <span><?= htmlspecialchars($program['program_name']) ?></span></div>
        <div class="meta-item"><strong>College</strong> <span><?= htmlspecialchars($program['college_name']) ?></span></div>
        <div class="meta-item"><strong>Report Date</strong> <span><?= date('F d, Y') ?></span></div>
        <div class="meta-item"><strong>Generated By</strong> <span><?= htmlspecialchars($_SESSION['full_name']) ?></span></div>
    </div>

    <h2>I. Quantitative Assessment (Survey Ratings)</h2>
    <table>
        <thead>
            <tr>
                <th>Area</th>
                <th>Description</th>
                <th style="text-align:center;">Average Rating</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($ratings as $r): 
                $score = $r['avg_rating'] ? number_format($r['avg_rating'], 2) : '-';
                $cls = ($r['avg_rating'] >= 4) ? 'score-high' : (($r['avg_rating'] > 0 && $r['avg_rating'] < 3) ? 'score-low' : '');
            ?>
            <tr>
                <td style="font-weight:600;">Area <?= $r['area_no'] ?></td>
                <td><?= htmlspecialchars($r['area_title']) ?></td>
                <td class="score-cell <?= $cls ?>"><?= $score ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>II. Qualitative Findings (Document Feedback)</h2>
    <?php if(empty($findings)): ?>
        <p style="color:#64748b; font-style:italic;">No qualitative findings or feedback recorded.</p>
    <?php else: ?>
        <?php foreach($findings as $area_id => $area_findings): 
            $area_info = $area_findings[0];
        ?>
        <div style="margin-bottom: 25px;">
            <h3 style="font-size:16px; color:#334155; margin-bottom:10px; background:#f1f5f9; padding:8px 12px; border-radius:4px;">Area <?= $area_info['area_no'] ?>: <?= htmlspecialchars($area_info['area_title']) ?></h3>
            <?php foreach($area_findings as $f): ?>
                <div class="finding-item">
                    <div class="finding-meta">
                        <span>Ref: <span class="doc-ref"><?= htmlspecialchars($f['file_name']) ?></span></span>
                        <span><?= date('M d, Y', strtotime($f['created_at'])) ?></span>
                    </div>
                    <div class="finding-text"><?= nl2br(htmlspecialchars($f['feedback_text'])) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="footer">
        Generated by BISU AACCUP System &bull; Confidential Document
    </div>
</body>
</html>
