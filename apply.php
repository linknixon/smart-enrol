<?php
session_start();
require 'config/db.php';

// Fetch dynamic configurations
$school_name = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_logo = getSetting($pdo, 'school_logo', 'https://www.ncedges.com/wp-content/uploads/2026/02/mutosp-logo.png');

$preSelectedClass = '';
if (isset($_GET['class'])) {
    $c = strtoupper($_GET['class']);
    if (in_array($c, ['S1', 'S2', 'S3', 'S4', 'S5', 'S6'])) {
        $preSelectedClass = $c;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Online Admission Application | <?= htmlspecialchars($school_name) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="css/style.css" rel="stylesheet">
</head>
<body>

<!-- Dynamic Background Slideshow -->
<div class="bg-slideshow-container">
    <div class="bg-slide active" style="background-image: url('images/campus.png');"></div>
    <div class="bg-slide" style="background-image: url('images/students.png');"></div>
    <div class="bg-slide" style="background-image: url('images/lab.png');"></div>
</div>

<div class="form-wizard-card">
    <h3 class="text-center mb-1">Admissions Application Portal</h3>
    <p class="text-center text-slate-500 text-xs mb-4">Complete all steps to submit your application to <?= htmlspecialchars($school_name) ?></p>

    <!-- Progress Steps -->
    <div class="progress-container mb-4">
        <div class="progress-step active" data-step="0">1</div>
        <div class="progress-step" data-step="1">2</div>
        <div class="progress-step" data-step="2">3</div>
        <div class="progress-line"></div>
        <div class="progress-line-fill"></div>
    </div>

    <form id="admissionForm" action="submit.php" method="POST" enctype="multipart/form-data">

        <!-- Step 1: Bio Data & Class selection -->
        <fieldset class="active">
            <h5 class="mb-3"><i class="fa-solid fa-user text-success"></i> Step 1: Student Bio Data</h5>
            
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Surname</label>
                    <input class="form-control" name="surname" placeholder="Surname" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">First Name</label>
                    <input class="form-control" name="firstname" placeholder="First Name" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Other Names (Optional)</label>
                    <input class="form-control" name="othernames" placeholder="Other Names">
                </div>
            </div>

            <div class="row g-3 mt-2">
                <div class="col-md-4">
                    <label class="form-label">Gender</label>
                    <select class="form-select" name="sex" required>
                        <option value="">Select Sex</option>
                        <option>Male</option>
                        <option>Female</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" class="form-control" name="dob" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Religion</label>
                    <select class="form-select" name="religion" required>
                        <option value="">Select Religion</option>
                        <option>Catholic</option>
                        <option>Anglican</option>
                        <option>Pentecostal / Born Again</option>
                        <option>Muslim</option>
                        <option>Seventh Day Adventist (SDA)</option>
                        <option>Other</option>
                    </select>
                </div>
            </div>

            <div class="row g-3 mt-2">
                <div class="col-md-6">
                    <label class="form-label font-bold text-success">Class of Entry</label>
                    <select class="form-select border-success" name="class_of_entry" id="classOfEntry" required>
                        <option value="">Select Target Class</option>
                        <option value="S1" <?= $preSelectedClass === 'S1' ? 'selected' : '' ?>>Senior One (S1)</option>
                        <option value="S2" <?= $preSelectedClass === 'S2' ? 'selected' : '' ?>>Senior Two (S2)</option>
                        <option value="S3" <?= $preSelectedClass === 'S3' ? 'selected' : '' ?>>Senior Three (S3)</option>
                        <option value="S4" <?= $preSelectedClass === 'S4' ? 'selected' : '' ?>>Senior Four (S4)</option>
                        <option value="S5" <?= $preSelectedClass === 'S5' ? 'selected' : '' ?>>Senior Five (S5)</option>
                        <option value="S6" <?= $preSelectedClass === 'S6' ? 'selected' : '' ?>>Senior Six (S6)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Upload Passport Photograph</label>
                    <input type="file" class="form-control" name="passport" accept="image/*" required>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-4">
                <button type="button" class="btn btn-primary next-btn">Next Step <i class="fa-solid fa-arrow-right"></i></button>
            </div>
        </fieldset>

        <!-- Step 2: Academic Credentials (Dynamic Content toggled by class selection) -->
        <fieldset>
            <h5 class="mb-3"><i class="fa-solid fa-graduation-cap text-success"></i> Step 2: Academic History & Documents</h5>

            <!-- 2.A: Senior One (PLE) -->
            <div id="pleContainer" style="display: none;">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Former Primary School</label>
                        <input class="form-control" name="former_school_s1" placeholder="Former Primary School">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">PLE Index Number</label>
                        <input class="form-control font-monospace" name="index_number_s1" placeholder="PLE Index Number">
                    </div>
                </div>

                <p class="text-xs font-semibold text-slate-500 uppercase mt-4 mb-2 tracking-wider">PLE Subject Grade Breakdown</p>
                <div class="row g-3 bg-slate-50 p-3 rounded-lg border border-slate-200">
                    <?php
                    $subjects = ['english'=>'English','mathematics'=>'Mathematics','sst'=>'Social Studies','science'=>'Science'];
                    foreach($subjects as $key=>$label){
                        echo "<div class='col-md-3'>";
                        echo "<label class='form-label text-xs font-semibold'>$label</label>";
                        echo "<select name='$key' class='form-select form-select-sm ple-grade-select'>";
                        foreach(['D1','D2','C3','C4','C5','C6','P7','P8','F9'] as $g){
                            echo "<option>$g</option>";
                        }
                        echo "</select></div>";
                    }
                    ?>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-md-4">
                        <label class="form-label">PLE Aggregates (Autocalculated)</label>
                        <input type="number" class="form-control font-bold text-success bg-light" name="aggregates_s1" min="4" max="36" readonly placeholder="Total Aggregates">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Attach PLE Result Slip (PDF or Image)</label>
                        <input type="file" class="form-control" name="result_slip_s1" accept=".pdf,image/*">
                    </div>
                </div>
            </div>

            <!-- 2.B: Senior Five (UCE) -->
            <div id="uceContainer" style="display: none;">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Former UCE School</label>
                        <input class="form-control" name="former_school_s5" placeholder="Former School name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">UCE Index Number</label>
                        <input class="form-control font-monospace" name="index_number_s5" placeholder="UCE Index Number">
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-md-4">
                        <label class="form-label">UCE Aggregates</label>
                        <input type="number" class="form-control" name="aggregates_s5" min="8" max="54" placeholder="Best 8 subjects total">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Division Obtained</label>
                        <select class="form-select" name="division_s5">
                            <option value="">Select Division</option>
                            <option>Division 1</option>
                            <option>Division 2</option>
                            <option>Division 3</option>
                            <option>Division 4</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Desired combination</label>
                        <select class="form-select font-bold" name="combination_s5">
                            <option value="">Select combination</option>
                            <optgroup label="Science combinations">
                                <option>PCM (Physics, Chemistry, Mathematics)</option>
                                <option>PCB (Physics, Chemistry, Biology)</option>
                                <option>BCM (Biology, Chemistry, Mathematics)</option>
                                <option>PEM (Physics, Economics, Mathematics)</option>
                                <option>MEG (Mathematics, Economics, Geography)</option>
                                <option>HEM (History, Economics, Mathematics)</option>
                            </optgroup>
                            <optgroup label="Arts combinations">
                                <option>HEG (History, Economics, Geography)</option>
                                <option>HEL (History, Economics, Literature)</option>
                                <option>HLD (History, Literature, Divinity)</option>
                                <option>LEG (Literature, Economics, Geography)</option>
                                <option>GEA (Geography, Entrepreneurship, Art)</option>
                            </optgroup>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-md-12">
                        <label class="form-label">Attach UCE Result Slip (PDF or Image)</label>
                        <input type="file" class="form-control" name="result_slip_s5" accept=".pdf,image/*">
                    </div>
                </div>
            </div>

            <!-- 2.C: Other Classes Transfer (S2, S3, S4, S6) -->
            <div id="transferContainer" style="display: none;">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Former School Name</label>
                        <input class="form-control" name="former_school_transfer" placeholder="Name of previous school">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last Class Attended</label>
                        <select class="form-select" name="last_class_attended">
                            <option value="">Select Last Class</option>
                            <option>Senior One (S1)</option>
                            <option>Senior Two (S2)</option>
                            <option>Senior Three (S3)</option>
                            <option>Senior Five (S5)</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-md-12">
                        <label class="form-label">Reason for seeking transfer to Mutolere SS</label>
                        <textarea class="form-control" name="transfer_reason" rows="3" placeholder="Provide detailed reason for transfer..."></textarea>
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-md-12">
                        <label class="form-label">Attach Report Card / Academic Transcript (PDF or Image)</label>
                        <input type="file" class="form-control" name="result_slip_transfer" accept=".pdf,image/*">
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between mt-4">
                <button type="button" class="btn btn-secondary prev-btn"><i class="fa-solid fa-arrow-left"></i> Previous</button>
                <button type="button" class="btn btn-primary next-btn">Next Step <i class="fa-solid fa-arrow-right"></i></button>
            </div>
        </fieldset>

        <!-- Step 3: Parent/Guardian & Confirmation -->
        <fieldset>
            <h5 class="mb-3"><i class="fa-solid fa-user-friends text-success"></i> Step 3: Parent / Guardian Details</h5>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Parent / Guardian Full Name</label>
                    <input class="form-control" name="parent_name" placeholder="Full name of parent/guardian" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone Number / WhatsApp Contact</label>
                    <input class="form-control font-monospace" name="parent_phone" placeholder="e.g. 0772123456 or +256772123456" required>
                </div>
            </div>

            <div class="row g-3 mt-2">
                <div class="col-md-6">
                    <label class="form-label">National ID Number (NIN)</label>
                    <input class="form-control font-monospace" name="parent_nin" placeholder="14 character NIRA NIN" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Parent Email Address</label>
                    <input type="email" class="form-control" name="parent_email" placeholder="e.g. parent@example.com" required>
                </div>
            </div>

            <div class="row g-3 mt-2">
                <div class="col-md-12">
                    <label class="form-label">Physical Residential Address</label>
                    <textarea class="form-control" name="parent_address" rows="2" placeholder="Village / Town / District / Country" required></textarea>
                </div>
            </div>

            <div class="form-check mt-4 mb-3">
                <input class="form-check-input" type="checkbox" required id="confirmCheck">
                <label class="form-check-label text-xs text-slate-600" for="confirmCheck">
                    I verify and confirm that the information provided is correct, and I agree to submit this application to St. Paul's Mutolere Secondary School.
                </label>
            </div>

            <div class="d-flex justify-content-between mt-4">
                <button type="button" class="btn btn-secondary prev-btn"><i class="fa-solid fa-arrow-left"></i> Previous</button>
                <button type="submit" class="btn btn-success">Submit Application <i class="fa-solid fa-paper-plane"></i></button>
            </div>
        </fieldset>

    </form>
</div>

<script>
// Dynamic aggregation calculation
const gradeValues = { "D1":1,"D2":2,"C3":3,"C4":4,"C5":5,"C6":6,"P7":7,"P8":8,"F9":9 };
const selects = document.querySelectorAll(".ple-grade-select");
const aggregateInput = document.querySelector("input[name='aggregates_s1']");

function calculateAggregates() {
    let total = 0;
    selects.forEach(sel => total += gradeValues[sel.value]);
    if (aggregateInput) {
        aggregateInput.value = total;
    }
}
selects.forEach(sel => sel.addEventListener("change", calculateAggregates));
calculateAggregates();

// Wizard logic and dynamic step field configurations
const classOfEntrySelect = document.getElementById("classOfEntry");
const pleContainer = document.getElementById("pleContainer");
const uceContainer = document.getElementById("uceContainer");
const transferContainer = document.getElementById("transferContainer");

function toggleClassFields() {
    const val = classOfEntrySelect.value;
    
    // Hide all academic containers
    pleContainer.style.display = "none";
    uceContainer.style.display = "none";
    transferContainer.style.display = "none";
    
    // Remove required attributes from conditional containers to prevent validation locks
    pleContainer.querySelectorAll("input, select").forEach(el => el.removeAttribute("required"));
    uceContainer.querySelectorAll("input, select").forEach(el => el.removeAttribute("required"));
    transferContainer.querySelectorAll("input, select, textarea").forEach(el => el.removeAttribute("required"));
    
    // Activate inputs and required indicators for target class
    if (val === "S1") {
        pleContainer.style.display = "block";
        pleContainer.querySelectorAll("input, select").forEach(el => {
            if (el.name !== "othernames") el.setAttribute("required", "required");
        });
    } else if (val === "S5") {
        uceContainer.style.display = "block";
        uceContainer.querySelectorAll("input, select").forEach(el => {
            el.setAttribute("required", "required");
        });
    } else if (val === "S2" || val === "S3" || val === "S4" || val === "S6") {
        transferContainer.style.display = "block";
        transferContainer.querySelectorAll("input, select, textarea").forEach(el => {
            el.setAttribute("required", "required");
        });
    }
}

classOfEntrySelect.addEventListener("change", toggleClassFields);
toggleClassFields();

// Multi-step navigation logic
const fieldsets = document.querySelectorAll('fieldset');
let current = 0;
const steps = document.querySelectorAll('.progress-step');
const lineFill = document.querySelector('.progress-line-fill');

function showStep(index){
    fieldsets.forEach((fs,i)=> fs.classList.toggle('active', i===index));
    steps.forEach((s,i)=> s.classList.toggle('active', i<=index));
    lineFill.style.width = (index/(fieldsets.length-1))*100 + '%';
}

function validateStep(index) {
    const fields = fieldsets[index].querySelectorAll("input, select, textarea");
    for (let inp of fields) {
        inp.setCustomValidity("");

        // Check general HTML5 validity rules
        if (!inp.checkValidity()) {
            inp.reportValidity();
            return false;
        }

        // Custom validation: Parent Phone format (Uganda standard)
        if (inp.name === "parent_phone") {
            const phoneVal = inp.value.trim();
            const phoneRegex = /^(?:\+256|256|0)[37][0-9]{8}$/;
            if (!phoneRegex.test(phoneVal)) {
                inp.setCustomValidity("Please enter a valid Uganda phone number (e.g. 0772123456 or +256772123456).");
                inp.reportValidity();
                return false;
            }
        }

        // Custom validation: Parent NIN (Uganda NIRA standard)
        if (inp.name === "parent_nin") {
            const ninVal = inp.value.trim().toUpperCase();
            const ninRegex = /^(CM|CF|RM|RF)[0-9A-Z]{12}$/;
            if (!ninRegex.test(ninVal)) {
                inp.setCustomValidity("Please enter a valid Ugandan National ID Number (NIN) of 14 characters starting with CM, CF, RM, or RF.");
                inp.reportValidity();
                return false;
            }
        }
    }
    return true;
}

// Reset custom error validations upon entry
document.querySelectorAll("input, select, textarea").forEach(inp => {
    inp.addEventListener("input", function() {
        this.setCustomValidity("");
    });
    inp.addEventListener("change", function() {
        this.setCustomValidity("");
    });
});

document.querySelectorAll('.next-btn').forEach(btn => {
    btn.addEventListener('click', ()=>{
        if (validateStep(current)) {
            if(current < fieldsets.length-1){ current++; showStep(current);}
        }
    });
});

document.querySelectorAll('.prev-btn').forEach(btn => {
    btn.addEventListener('click', ()=>{
        if(current > 0){ current--; showStep(current);}
    });
});

document.getElementById('admissionForm').addEventListener('submit', function(e) {
    if (!validateStep(current)) {
        e.preventDefault();
    }
});

// Slideshow interval
let activeSlideIndex = 0;
const slides = document.querySelectorAll('.bg-slide');
setInterval(() => {
    if (slides.length > 0) {
        slides[activeSlideIndex].classList.remove('active');
        activeSlideIndex = (activeSlideIndex + 1) % slides.length;
        slides[activeSlideIndex].classList.add('active');
    }
}, 5000);
</script>

</body>
</html>
