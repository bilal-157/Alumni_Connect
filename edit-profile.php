<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$errors  = [];
$success = false;

// ── Fetch existing data ──────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.role, u.created_at,
           COALESCE(p.batch,        '') as batch,
           COALESCE(p.session,      '') as session,
           COALESCE(p.degree,       '') as degree,
           COALESCE(p.company,      '') as company,
           COALESCE(p.job_title,    '') as job_title,
           COALESCE(p.phone,        '') as phone,
           COALESCE(p.linkedin_url, '') as linkedin_url,
           COALESCE(p.bio,          '') as bio,
           COALESCE(p.profile_image,'default.png') as profile_image,
           COALESCE(p.skills,       '') as skills
    FROM users u
    LEFT JOIN profiles p ON u.id = p.user_id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$pu = $stmt->fetch();

if (!$pu) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// Create profile row if it doesn't exist yet
$checkProfile = $pdo->prepare("SELECT user_id FROM profiles WHERE user_id = ?");
$checkProfile->execute([$user_id]);
if (!$checkProfile->fetch()) {
    $pdo->prepare("INSERT INTO profiles (user_id) VALUES (?)")->execute([$user_id]);
}

$imgPath = 'uploads/profiles/' . $pu['profile_image'];
$imgUrl  = (file_exists($imgPath) && $pu['profile_image'] !== 'default.png')
    ? BASE_URL . '/' . $imgPath
    : 'https://ui-avatars.com/api/?name=' . urlencode($pu['name']) . '&background=e94560&color=fff&size=170';

// ── Handle form submission ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name        = trim($_POST['name']        ?? '');
    $batch       = trim($_POST['batch']       ?? '');
    $session     = trim($_POST['session']     ?? '');
    $degree      = trim($_POST['degree']      ?? '');
    $company     = trim($_POST['company']     ?? '');
    $job_title   = trim($_POST['job_title']   ?? '');
    $phone       = trim($_POST['phone']       ?? '');
    $linkedin    = trim($_POST['linkedin_url']?? '');
    $bio         = trim($_POST['bio']         ?? '');
    // skills comes from the hidden field (populated by the tag JS)
    $skills      = trim($_POST['skills_hidden'] ?? '');

    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if ($linkedin !== '' && !filter_var($linkedin, FILTER_VALIDATE_URL)) {
        $errors[] = 'LinkedIn URL is not valid.';
    }

    // Profile image upload
    $new_image = $pu['profile_image'];
    if (!empty($_FILES['profile_image']['name'])) {
        $allowed  = ['jpg','jpeg','png','gif','webp'];
        $ext      = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        $maxSize  = 5 * 1024 * 1024;

        if (!in_array($ext, $allowed)) {
            $errors[] = 'Profile image must be JPG, PNG, GIF, or WebP.';
        } elseif ($_FILES['profile_image']['size'] > $maxSize) {
            $errors[] = 'Profile image must be under 5 MB.';
        } else {
            $fileName = 'user_' . $user_id . '_' . time() . '.' . $ext;
            $dest     = 'uploads/profiles/' . $fileName;
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $dest)) {
                // Delete old image
                if ($pu['profile_image'] !== 'default.png' && file_exists('uploads/profiles/' . $pu['profile_image'])) {
                    unlink('uploads/profiles/' . $pu['profile_image']);
                }
                $new_image = $fileName;
                $_SESSION['user_image'] = $fileName;
            } else {
                $errors[] = 'Could not save the uploaded image. Check folder permissions.';
            }
        }
    }

    if (empty($errors)) {
        // Update users table
        $pdo->prepare("UPDATE users SET name = ? WHERE id = ?")
            ->execute([$name, $user_id]);

        // Update profiles table (row already guaranteed to exist above)
        $pdo->prepare("
            UPDATE profiles SET
                batch = ?, session = ?, degree = ?, company = ?, job_title = ?,
                phone = ?, linkedin_url = ?, bio = ?, profile_image = ?, skills = ?
            WHERE user_id = ?
        ")->execute([
            $batch, $session, $degree, $company, $job_title,
            $phone, $linkedin, $bio, $new_image, $skills,
            $user_id
        ]);

        $success = true;

        // Refresh $pu
        $stmt->execute([$user_id]);
        $pu = $stmt->fetch();

        $imgPath = 'uploads/profiles/' . $pu['profile_image'];
        $imgUrl  = (file_exists($imgPath) && $pu['profile_image'] !== 'default.png')
            ? BASE_URL . '/' . $imgPath
            : 'https://ui-avatars.com/api/?name=' . urlencode($pu['name']) . '&background=e94560&color=fff&size=170';
    }
}

$currentYear = date('Y');
$years = range(1995, $currentYear + 1);
rsort($years);

$degrees = [
    'BS Computer Science','BS Software Engineering',
    'BS Information Technology','BS Artificial Intelligence',
    'BS Data Science','BS Cyber Security',
    'BS Electrical Engineering','BS Civil Engineering',
    'BS Mechanical Engineering','BBA','BS Mathematics',
    'BS Physics','BS English','BS Mass Communication',
    'BS Psychology','BS Economics','LLB'
];
?>
<?php include 'includes/header.php'; ?>

<style>
:root {
    --primary:       #e94560;
    --primary-dark:  #c73652;
    --primary-light: #fce4e8;
    --bg-dark:       #1a1a2e;
    --bg-darker:     #16213e;
    --text-main:     #1f2937;
    --text-muted:    #6b7280;
    --text-light:    #94a3b8;
    --border:        #e5e7eb;
    --radius-lg:     16px;
    --radius-md:     12px;
    --radius-sm:     8px;
    --shadow-md:     0 4px 20px rgba(0,0,0,.07);
    --shadow-lg:     0 8px 30px rgba(0,0,0,.1);
    --transition:    all 0.25s cubic-bezier(0.4,0,0.2,1);
}

/* Hero */
.edit-hero {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #1a1a2e 100%);
    border-radius: var(--radius-lg);
    padding: 36px 32px;
    color: #fff;
    margin-bottom: 28px;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
}
.edit-hero::before {
    content: '';
    position: absolute; top: -40%; right: -8%;
    width: 380px; height: 380px;
    background: radial-gradient(circle, rgba(233,69,96,.15) 0%, transparent 70%);
    border-radius: 50%; pointer-events: none;
}

/* Avatar */
.avatar-wrap { position: relative; width: 120px; flex-shrink: 0; cursor: pointer; }
.avatar-wrap img {
    width: 120px; height: 120px; border-radius: 50%; object-fit: cover;
    border: 4px solid rgba(233,69,96,.4); display: block;
    box-shadow: 0 4px 20px rgba(233,69,96,.25);
    transition: var(--transition);
}
.avatar-wrap:hover img { border-color: var(--primary); transform: scale(1.03); }
.avatar-overlay {
    position: absolute; inset: 0; border-radius: 50%;
    background: rgba(0,0,0,.45);
    display: flex; align-items: center; justify-content: center;
    opacity: 0; transition: .2s; color: #fff; font-size: 22px;
}
.avatar-wrap:hover .avatar-overlay { opacity: 1; }

/* Cards */
.section-card {
    border-radius: var(--radius-md);
    margin-bottom: 22px;
    border: none;
    box-shadow: var(--shadow-md);
    background: #fff;
    overflow: hidden;
    transition: var(--transition);
}
.section-card:hover { box-shadow: var(--shadow-lg); transform: translateY(-2px); }
.section-head {
    padding: 15px 22px;
    border-bottom: 1px solid #f3f4f6;
    font-size: 13px; font-weight: 700;
    background: #fff; color: var(--text-main);
    display: flex; align-items: center; gap: 8px;
}
.section-body { padding: 22px; }

/* Section title inside card */
.section-title {
    font-size: 12px; font-weight: 700; color: var(--primary);
    text-transform: uppercase; letter-spacing: .05em;
    padding-bottom: 8px; border-bottom: 2px solid var(--primary-light);
    margin-bottom: 18px;
}

/* Tabs */
.tab-pill {
    border-radius: 20px; font-size: 13px; font-weight: 500;
    padding: 7px 18px; border: 1.5px solid var(--border);
    color: var(--text-muted); background: #fff; cursor: pointer; transition: .2s;
}
.tab-pill.active, .tab-pill:hover {
    background: var(--primary); color: #fff; border-color: var(--primary);
}
.tab-section { display: none; }
.tab-section.active { display: block; }

/* Form controls */
.form-label { font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
.form-control, .form-select {
    border-radius: var(--radius-sm);
    border: 1.5px solid var(--border);
    font-size: 14px; padding: 10px 14px;
    color: var(--text-main);
    transition: border-color .2s, box-shadow .2s;
}
.form-control::placeholder { color: #9ca3af; font-size: 13px; }
.form-control:focus, .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(233,69,96,.1);
    outline: none;
}
.form-control:disabled { background: #f9fafb; color: var(--text-muted); cursor: not-allowed; }
textarea.form-control { resize: vertical; min-height: 130px; line-height: 1.6; }

/* Skill tags */
.skill-tag {
    display: inline-flex; align-items: center; gap: 4px;
    background: var(--primary-light); color: var(--primary);
    border-radius: 20px; padding: 4px 12px;
    font-size: 12px; font-weight: 600; margin: 3px;
    border: 1px solid rgba(233,69,96,.15);
    transition: var(--transition);
}
.skill-tag:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(233,69,96,.2); }
.skill-tag .remove {
    cursor: pointer; font-weight: 800; font-size: 13px;
    line-height: 1; color: rgba(233,69,96,.7);
}
.skill-tag .remove:hover { color: var(--primary); }

/* Buttons */
.btn-save {
    background: var(--primary); color: #fff;
    border: none; border-radius: var(--radius-sm);
    padding: 11px 30px; font-weight: 700; font-size: 14px;
    transition: var(--transition);
    display: inline-flex; align-items: center; gap: 6px;
    box-shadow: 0 4px 14px rgba(233,69,96,.3);
}
.btn-save:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(233,69,96,.4); color: #fff; }

/* Alerts */
.alert { border-radius: var(--radius-md); font-size: 14px; border: none; padding: 15px 20px; margin-bottom: 22px; }
.alert-success { background: #f0fdf4; color: #166534; border-left: 4px solid #22c55e; }
.alert-danger  { background: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444; }

@media (max-width: 768px) {
    .edit-hero { padding: 26px 18px; }
    .section-body { padding: 18px; }
}
</style>

<div class="container pb-5">

    <!-- Hero -->
    <div class="edit-hero">
        <div class="d-flex flex-column flex-md-row align-items-center gap-4">
            <div class="avatar-wrap" onclick="document.getElementById('imgInput').click()">
                <img id="avatarPreview" src="<?= $imgUrl ?>" alt="Profile photo">
                <div class="avatar-overlay"><i class="bi bi-camera-fill"></i></div>
            </div>
            <div class="flex-grow-1">
                <h2 class="fw-bold mb-1" style="font-size:1.6rem;letter-spacing:-.02em">
                    <?= sanitize($pu['name']) ?>
                </h2>
                <p class="mb-4" style="color:var(--text-light);font-size:14px">
                    Update your alumni profile — changes are visible to the whole network.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <button form="editForm" type="submit" class="btn-save">
                        <i class="bi bi-check2-circle"></i> Save Changes
                    </button>
                    <a href="profile.php?id=<?= $user_id ?>" class="btn btn-outline-light btn-sm fw-semibold" style="border-radius:8px;padding:10px 20px">
                        <i class="bi bi-eye me-1"></i> View Profile
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($success): ?>
        <div class="alert alert-success d-flex align-items-center gap-3">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div class="flex-grow-1"><strong>Profile updated!</strong> Your changes have been saved.</div>
            <a href="profile.php?id=<?= $user_id ?>" class="btn btn-sm btn-success fw-semibold" style="border-radius:6px;padding:6px 16px">
                View Profile <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <div class="d-flex gap-3">
                <i class="bi bi-exclamation-triangle-fill fs-5 mt-1"></i>
                <div>
                    <strong>Please fix the following:</strong>
                    <ul class="mb-0 mt-1 ps-3">
                        <?php foreach ($errors as $e): ?>
                            <li><?= sanitize($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <form id="editForm" method="POST" enctype="multipart/form-data">

        <!-- Hidden file input triggered by avatar click -->
        <input type="file" name="profile_image" id="imgInput"
               class="d-none" accept="image/*">

        <!-- Hidden field that holds the comma-separated skills string -->
        <input type="hidden" name="skills_hidden" id="skillsHidden"
               value="<?= sanitize($pu['skills']) ?>">

        <div class="row g-4">

            <!-- ── Left: sticky avatar card ─────────────────────────────── -->
            <div class="col-lg-3">
                <div class="card p-3 text-center sticky-top" style="top:80px;border-radius:var(--radius-md);box-shadow:var(--shadow-md);border:none">
                    <div class="avatar-wrap mx-auto mb-3" onclick="document.getElementById('imgInput').click()" style="width:100px">
                        <img id="avatarPreview2" src="<?= $imgUrl ?>"
                             style="width:100px;height:100px"
                             alt="<?= sanitize($pu['name']) ?>">
                        <div class="avatar-overlay"><i class="bi bi-camera-fill"></i></div>
                    </div>
                    <h6 class="fw-bold mb-0"><?= sanitize($pu['name']) ?></h6>
                    <p class="text-muted mb-2" style="font-size:12px"><?= sanitize($pu['email']) ?></p>
                    <?php if ($pu['batch']): ?>
                        <span class="skill-tag">Batch <?= sanitize($pu['batch']) ?></span>
                    <?php endif; ?>
                    <?php if ($pu['degree']): ?>
                        <p class="text-muted mt-2 mb-0" style="font-size:11px"><?= sanitize($pu['degree']) ?></p>
                    <?php endif; ?>
                    <hr class="my-3">
                    <p class="text-muted mb-2" style="font-size:11px">
                        <i class="bi bi-camera me-1"></i>Click photo to change
                    </p>
                    <button type="submit" class="btn btn-danger btn-sm w-100 fw-semibold">
                        <i class="bi bi-save me-1"></i> Save Changes
                    </button>
                </div>
            </div>

            <!-- ── Right: tabbed form ────────────────────────────────────── -->
            <div class="col-lg-9">
                <div class="section-card">
                    <div class="section-body">

                        <!-- Tab pills -->
                        <div class="d-flex gap-2 flex-wrap mb-4">
                            <button type="button" class="tab-pill active" onclick="switchTab('education', this)">
                                <i class="bi bi-mortarboard me-1"></i> Education
                            </button>
                            <button type="button" class="tab-pill" onclick="switchTab('professional', this)">
                                <i class="bi bi-briefcase me-1"></i> Professional
                            </button>
                            <button type="button" class="tab-pill" onclick="switchTab('contact', this)">
                                <i class="bi bi-telephone me-1"></i> Contact
                            </button>
                            <button type="button" class="tab-pill" onclick="switchTab('about', this)">
                                <i class="bi bi-person-badge me-1"></i> About
                            </button>
                        </div>

                        <!-- Tab: Education -->
                        <div id="tab-education" class="tab-section active">
                            <div class="section-title"><i class="bi bi-mortarboard me-1"></i> Education</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Batch (Graduation Year)</label>
                                    <select name="batch" class="form-select">
                                        <option value="">Select Year</option>
                                        <?php foreach ($years as $year): ?>
                                            <option value="<?= $year ?>" <?= ($pu['batch'] == $year) ? 'selected' : '' ?>>
                                                <?= $year ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Session</label>
                                    <input type="text" name="session" class="form-control"
                                           placeholder="e.g. 2020–2024"
                                           value="<?= sanitize($pu['session']) ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Degree Program</label>
                                    <select name="degree" class="form-select">
                                        <option value="">Select Degree</option>
                                        <?php foreach ($degrees as $d): ?>
                                            <option value="<?= $d ?>" <?= ($pu['degree'] === $d) ? 'selected' : '' ?>>
                                                <?= $d ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Tab: Professional -->
                        <div id="tab-professional" class="tab-section">
                            <div class="section-title"><i class="bi bi-briefcase me-1"></i> Professional</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Current Job Title</label>
                                    <input type="text" name="job_title" class="form-control"
                                           placeholder="e.g. Software Engineer"
                                           value="<?= sanitize($pu['job_title']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Company / Organization</label>
                                    <input type="text" name="company" class="form-control"
                                           placeholder="e.g. Google, Systems Ltd."
                                           value="<?= sanitize($pu['company']) ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Skills</label>
                                    <input type="text" id="skillsInput" class="form-control"
                                           placeholder="Type a skill and press Enter or comma"
                                           autocomplete="off">
                                    <div id="skillTags" class="mt-2"></div>
                                    <div class="form-text">Press Enter or comma to add · click × to remove</div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab: Contact -->
                        <div id="tab-contact" class="tab-section">
                            <div class="section-title"><i class="bi bi-telephone me-1"></i> Contact</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text" style="border-radius:8px 0 0 8px">
                                            <i class="bi bi-phone"></i>
                                        </span>
                                        <input type="tel" name="phone" class="form-control"
                                               placeholder="+92 300 1234567"
                                               value="<?= sanitize($pu['phone']) ?>"
                                               style="border-radius:0 8px 8px 0">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">LinkedIn Profile URL</label>
                                    <div class="input-group">
                                        <span class="input-group-text" style="border-radius:8px 0 0 8px;background:#0077b5;color:#fff;border-color:#0077b5">
                                            <i class="bi bi-linkedin"></i>
                                        </span>
                                        <input type="url" name="linkedin_url" class="form-control"
                                               placeholder="https://linkedin.com/in/username"
                                               value="<?= sanitize($pu['linkedin_url']) ?>"
                                               style="border-radius:0 8px 8px 0">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="alert alert-light py-2 small border mb-0" style="border-radius:8px">
                                        <i class="bi bi-shield-check text-success me-1"></i>
                                        Contact info is only visible to logged-in alumni.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab: About -->
                        <div id="tab-about" class="tab-section">
                            <div class="section-title"><i class="bi bi-person-badge me-1"></i> About You</div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Name</label>
                                    <input type="text" name="name" class="form-control"
                                           value="<?= sanitize($pu['name']) ?>"
                                           placeholder="Your full name" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control"
                                           value="<?= sanitize($pu['email']) ?>"
                                           disabled title="Contact support to change your email">
                                    <div class="form-text"><i class="bi bi-lock me-1"></i>Email cannot be changed here</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Bio / About Me</label>
                                    <textarea name="bio" class="form-control" rows="6"
                                              placeholder="Tell the alumni community about yourself..."><?= sanitize($pu['bio']) ?></textarea>
                                    <div class="d-flex justify-content-between mt-1">
                                        <div class="form-text">Max 500 characters recommended</div>
                                        <div class="form-text" id="bioCount">0 / 500</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom action row -->
                        <div class="d-flex gap-2 mt-4 pt-3 border-top">
                            <button type="submit" class="btn-save">
                                <i class="bi bi-save"></i> Save Changes
                            </button>
                            <a href="profile.php?id=<?= $user_id ?>" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// ── Tab switching ────────────────────────────────────────────────────────────
function switchTab(name, btn) {
    document.querySelectorAll('.tab-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.tab-pill').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}

// ── Avatar preview (syncs both hero + sidebar previews) ──────────────────────
document.getElementById('imgInput').addEventListener('change', function () {
    if (!this.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('avatarPreview').src  = e.target.result;
        document.getElementById('avatarPreview2').src = e.target.result;
    };
    reader.readAsDataURL(this.files[0]);
});

// ── Skills tag system ────────────────────────────────────────────────────────
const skillsHidden = document.getElementById('skillsHidden');
const skillsInput  = document.getElementById('skillsInput');
const skillTags    = document.getElementById('skillTags');

// Load pre-existing skills from the hidden field (populated by PHP)
let skills = skillsHidden.value
    ? skillsHidden.value.split(',').map(s => s.trim()).filter(Boolean)
    : [];

function renderTags() {
    skillTags.innerHTML = skills.map((s, i) =>
        `<span class="skill-tag">${s.replace(/</g,'&lt;')}
            <span class="remove" onclick="removeSkill(${i})">&times;</span>
        </span>`
    ).join('');
    skillsHidden.value = skills.join(', ');
}

function removeSkill(i) { skills.splice(i, 1); renderTags(); }

skillsInput.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        const val = skillsInput.value.trim().replace(/,$/, '');
        if (val && !skills.includes(val)) { skills.push(val); renderTags(); }
        skillsInput.value = '';
    }
});

renderTags(); // render on page load

// ── Bio character counter ────────────────────────────────────────────────────
const bioEl    = document.querySelector('textarea[name="bio"]');
const bioCount = document.getElementById('bioCount');
function updateCount() {
    const len = bioEl.value.length;
    bioCount.textContent = len + ' / 500';
    bioCount.style.color = len > 500 ? '#e94560' : '#6b7280';
}
bioEl.addEventListener('input', updateCount);
updateCount();
</script>

<?php include 'includes/footer.php'; ?>