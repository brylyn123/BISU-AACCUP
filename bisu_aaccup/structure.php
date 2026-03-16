<?php 
session_start();
include 'config/db.php';
include 'nav.php'; 

$officials = [
    'President' => [
        [
            'name' => 'DR. ANTHONY M. PENASO',
            'title' => 'University President',
            'email' => 'up@bisu.edu.ph'
        ]
    ],
    'Vice Presidents' => [
        [
            'name' => 'DR. ARCELI M. HERNANDO',
            'title' => 'Vice President, Administration & Finance',
            'email' => 'avpfinance@bisu.edu.ph'
        ],
        [
            'name' => 'DR. MA. LILIBETH G. CASTIL',
            'title' => 'Vice President, Academics & Quality Assurance',
            'email' => 'vpacaqa@bisu.edu.ph'
        ],
        [
            'name' => 'DR. ZINA D. SAYSON',
            'title' => 'Vice President, Student Affairs & Services',
            'email' => 'vpsas@bisu.edu.ph'
        ],
        [
            'name' => 'DR. IVY CORAZON A. MANGAYA-AY',
            'title' => 'Vice President, Research, Innovation & Extension',
            'email' => 'vprie@bisu.edu.ph'
        ]
    ],
    'Campus Directors' => [
        ['name' => 'DR. JEAN F. NEBREA', 'title' => 'Campus Director, Main Campus', 'email' => 'jeanf@bisu.edu.ph'],
        ['name' => 'DR. MARIETTA M. MACALOLLOT', 'title' => 'Campus Director, Balilihan Campus', 'email' => 'mmac.olat@bisu.edu.ph'],
        ['name' => 'DR. PROCESO M. CASTIL', 'title' => 'Campus Director, Bilar Campus', 'email' => 'pmcastil@bisu.edu.ph'],
        ['name' => 'DR. ROXANNE P. ALTEA', 'title' => 'Campus Director, Calape Campus', 'email' => 'rpaltea@bisu.edu.ph'],
        ['name' => 'DR. LUZMIDA G. MACHETE', 'title' => 'Campus Director, Candijay Campus', 'email' => 'lgmachete@bisu.edu.ph'],
        ['name' => 'DR. CLEMENTE A. RUIZ', 'title' => 'Campus Director, Clarin Campus', 'email' => 'caruiz@bisu.edu.ph']
    ],
    'Office of the University President' => [
        ['name' => 'MR. ERIC JHON E. AUGUIS', 'title' => 'Executive Assistant II', 'email' => 'ejauguis@bisu.edu.ph'],
        ['name' => 'ATTY. VANESSA H. QUIJANO', 'title' => 'University Legal Officer', 'email' => 'vquijano@bisu.edu.ph'],
        ['name' => 'MS. BONA DEA L. PADRON', 'title' => 'Director, Planning & Strategic Foresight', 'email' => 'bpadron@bisu.edu.ph'],
        ['name' => 'DR. DENNIVENT A. RUIZ', 'title' => 'Presidential Assistant for Campus Safety', 'email' => 'dar@bisu.edu.ph'],
        ['name' => 'MRS. MAE REMEDIOS C. VIRTUCIO', 'title' => 'Information Officer II', 'email' => 'mcv@bisu.edu.ph'],
        ['name' => 'MS. MARIA PIO', 'title' => 'Public Information Officer', 'email' => 'mpi@bisu.edu.ph']
    ],
    'Office of the Vice President for Academics & Quality Assurance' => [
        ['name' => 'DR. BENJAMIN N. OMAMALIN', 'title' => 'University Director, Instruction', 'email' => 'bnomamalin@bisu.edu.ph'],
        ['name' => 'ENGR. ELAINE M. CEPE', 'title' => 'University Director, Quality Assurance', 'email' => 'emcepe@bisu.edu.ph'],
        ['name' => 'DR. FRANCIS A. DELUSA', 'title' => 'University Director, International Relations', 'email' => 'fadelusa@bisu.edu.ph'],
        ['name' => 'DR. SHIELA M. RANQUE', 'title' => 'Instructional Materials Dev. Center', 'email' => 'smranque@bisu.edu.ph'],
        ['name' => 'MRS. MISELISA S. OFAMEN', 'title' => 'University Director, Learning Resource Center', 'email' => 'msofamen@bisu.edu.ph'],
        ['name' => 'DR. VALENTINA D. RAMISO', 'title' => 'Administrative Officer V', 'email' => 'vdramiso@bisu.edu.ph']
    ],
    'Office of the Vice President for Research, Innovation, & Extension' => [
        ['name' => 'DR. JOSEPHINE B. NALZARO', 'title' => 'University Director, Research', 'email' => 'jbnalzaro@bisu.edu.ph'],
        ['name' => 'DR. LLOYD L. TEJANO', 'title' => 'University Director, Extension', 'email' => 'lltejano@bisu.edu.ph'],
        ['name' => 'DR. ANGELINE B. ELECIO', 'title' => 'University Director, Innovation & Tech. Support', 'email' => 'abe@bisu.edu.ph'],
        ['name' => 'DR. BERNABE M. MIJARES JR.', 'title' => 'University Director, Regional Consortia', 'email' => 'bmijares@bisu.edu.ph'],
        ['name' => 'DR. DARLENE ANGELICA A. LOQUIAS', 'title' => 'University Director, Publication', 'email' => 'dal@bisu.edu.ph'],
        ['name' => 'DR. JEROME P. MANATAD', 'title' => 'University Director, Fabrication Laboratory', 'email' => 'jmanatad@bisu.edu.ph'],
        ['name' => 'MR. C.J.T. HINGPIT', 'title' => 'University Director, Data Management & Analytics', 'email' => 'cjhingpit@bisu.edu.ph']
    ],
    'Office of the Vice President for Student Affairs & Services' => [
        ['name' => 'DR. JERALYN G. ALAGON', 'title' => 'University Director, Admissions & Scholarship', 'email' => 'jgalagon@bisu.edu.ph'],
        ['name' => 'DR. JONATHAN C. NERI', 'title' => 'University Director, Student Development', 'email' => 'jcneri@bisu.edu.ph'],
        ['name' => 'MRS. ADELYN C. IBARRA', 'title' => 'University Director, Registration', 'email' => 'acibarra@bisu.edu.ph'],
        ['name' => 'MRS. MA. DINA F. GOLOSINO', 'title' => 'University Director, Guidance & Counseling', 'email' => 'mdf@bisu.edu.ph'],
        ['name' => 'DR. PEARL DIANNE B. LOPEZ, MD', 'title' => 'University Director, Health & Wellness', 'email' => 'pdl@bisu.edu.ph'],
        ['name' => 'DR. LEMUEL P. ABDUL', 'title' => 'University Director, Socio-Cultural & Arts', 'email' => 'lpabdul@bisu.edu.ph'],
        ['name' => 'DR. VIVENCO L. CALIXTRO JR.', 'title' => 'University Director, Sports & Development', 'email' => 'vlc@bisu.edu.ph'],
        ['name' => 'DR. JOSEPH J. SALIGAN', 'title' => 'University Director, Housing & Alumni Relations', 'email' => 'jjsaligan@bisu.edu.ph']
    ],
    'Office of the Vice President for Administration & Finance' => [
        ['name' => 'ATTY. JOEL D. ZAMORA', 'title' => 'Chief Administrative Officer (Administration)', 'email' => 'jdzamora@bisu.edu.ph'],
        ['name' => 'MR. EUSEBIO M. FULLANTE, CPA', 'title' => 'Chief Administrative Officer (Finance)', 'email' => 'emfullante@bisu.edu.ph'],
        ['name' => 'MS. NANELYN D. WATE', 'title' => 'Supervising Administrative Officer (Admin)', 'email' => 'ndwate@bisu.edu.ph'],
        ['name' => 'MRS. ROSITA L. GALOGO', 'title' => 'Supervising Administrative Officer (Finance)', 'email' => 'rlgalogo@bisu.edu.ph'],
        ['name' => 'DR. MARILOU C. MICULOB', 'title' => 'University Director, Production & Business Services', 'email' => 'mcmiculob@bisu.edu.ph'],
        ['name' => 'MR. ELANO L. BAG-AO', 'title' => 'University Procurement Officer', 'email' => 'elbgao@bisu.edu.ph'],
        ['name' => 'DR. ROWELL G. OLAIVAR', 'title' => 'University Director, Human Resource Management', 'email' => 'rgo@bisu.edu.ph'],
        ['name' => 'MRS. CATHERENE B. PERIN', 'title' => 'University Director, Gender & Development', 'email' => 'cbperin@bisu.edu.ph'],
        ['name' => 'MR. JORGE C. NADERA', 'title' => 'Director, Disaster Risk Reduction Management', 'email' => 'jcnadera@bisu.edu.ph'],
        ['name' => 'ENGR. PHILLIP GLENN A. LIBAY', 'title' => 'University Director, Management Information Systems', 'email' => 'pglibay@bisu.edu.ph'],
        ['name' => 'MRS. JENNIFER C. ALCAIN', 'title' => 'Administrative Officer V (Supply)', 'email' => 'jcalcain@bisu.edu.ph'],
        ['name' => 'MR. ARTHUR O. YANA', 'title' => 'Administrative Officer V (Travel)', 'email' => 'aoyana@bisu.edu.ph'],
        ['name' => 'MRS. CERINA G. NACORDA', 'title' => 'Administrative Officer V (Records)', 'email' => 'cgnacorda@bisu.edu.ph'],
        ['name' => 'ENGR. ALVIN B. BESINGA', 'title' => 'Administrative Officer V (Gen. Services)', 'email' => 'abbesinga@bisu.edu.ph'],
        ['name' => 'MRS. MARIA VICTORIA M. JASPE', 'title' => 'Administrative Officer V (Budget)', 'email' => 'mvjaspe@bisu.edu.ph'],
        ['name' => 'MRS. AGNES B. CANDUG', 'title' => 'Administrative Officer V (Cashier)', 'email' => 'abcandug@bisu.edu.ph'],
        ['name' => 'MRS. MAR LU LUZETTE P. QUILATON, CPA', 'title' => 'University Director, Accounting', 'email' => 'mlpquilaton@bisu.edu.ph'],
        ['name' => 'MRS. MARIANNE A. LUNGAY', 'title' => 'University Director, Project Development & Mgmt', 'email' => 'malungay@bisu.edu.ph']
    ]
];

?>
<div class="structure-page">
    <header class="structure-hero">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <div class="hero-badge">Bohol Island State University</div>
            <h1>University Officials Directory</h1>
            <p>Meet the leaders, directors, and officers shaping the BISU Candijay campus across academe, student affairs, and administration.</p>
        </div>
    </header>

    <main class="structure-content">
        <?php foreach($officials as $section => $members): ?>
            <section class="structure-section">
                <div class="section-title">
                    <span class="section-label"><?= $section ?></span>
                </div>
                <div class="section-grid">
                    <?php foreach($members as $member): ?>
                        <article class="section-card">
                            <h3><?= $member['name'] ?></h3>
                            <p><?= $member['title'] ?></p>
                            <?php if(!empty($member['email'])): ?>
                                <a href="mailto:<?= $member['email'] ?>">
                                    <i class="fas fa-envelope"></i>
                                    <?= $member['email'] ?>
                                </a>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </main>
</div>

    <style>
        .structure-page {
            background: #fff;
            min-height: 100vh;
        }

        .structure-hero {
            position: relative;
            height: 220px;
            background: linear-gradient(120deg, rgba(78, 41, 136, 0.9), rgba(24, 38, 77, 0.9)), url('images/bisu.jpg') center/cover;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            padding: 0 20px;
        }

    .structure-hero::after {
        content: '';
        position: absolute;
        inset: 20px;
        border: 1px solid rgba(255,255,255,0.3);
        border-radius: 16px;
    }

    .hero-content {
        position: relative;
        max-width: 900px;
        z-index: 2;
    }

    .hero-badge {
        font-size: 0.85rem;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        background: rgba(255,255,255,0.2);
        padding: 6px 18px;
        border-radius: 999px;
        display: inline-flex;
        gap: 8px;
        align-items: center;
        margin-bottom: 10px;
        font-weight: 600;
    }

    .hero-content h1 {
        font-size: 2.8rem;
        margin-bottom: 15px;
        font-weight: 700;
    }

    .hero-content p {
        font-size: 1.1rem;
        opacity: 0.9;
        margin: 0 auto;
        max-width: 640px;
        line-height: 1.5;
    }

    .structure-content {
        padding: 60px 5% 120px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .structure-section {
        margin-bottom: 60px;
    }

    .section-title {
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .section-label {
        font-size: 1rem;
        font-weight: 700;
        letter-spacing: 1px;
        color: #6c63ff;
        border-bottom: 2px solid #6c63ff;
        padding-bottom: 4px;
    }

    .section-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 18px;
    }

    .section-card {
        background: linear-gradient(180deg, rgba(255,255,255,0.95) 0%, rgba(247,248,250,0.8) 100%);
        border-radius: 18px;
        padding: 20px;
        border: 1px solid rgba(99,110,114,0.1);
        box-shadow: 0 15px 45px rgba(18,18,51,0.09);
        transition: transform 0.25s ease, box-shadow 0.25s ease;
        position: relative;
        overflow: hidden;
    }

    .section-card::after {
        content: '';
        position: absolute;
        inset: 12px;
        border-radius: 14px;
        border: 1px solid rgba(108,99,255,0.15);
        pointer-events: none;
    }

    .section-card h3 {
        font-size: 1rem;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: #2d2d2d;
    }

    .section-card p {
        margin: 0 0 10px;
        color: #4d5562;
        font-size: 0.9rem;
    }

    .section-card a {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: #6c63ff;
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: lowercase;
    }

    .section-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 18px 45px rgba(18,18,51,0.2);
    }

    @media (max-width: 768px) {
        .hero-content h1 {
            font-size: 2.1rem;
        }

        .structure-content {
            padding: 40px 24px 80px;
        }
    }
</style>

<?php include 'footer.php'; ?>
