<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>S1 Online Application | St. Paul's Mutolere SS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../css/style.css" rel="stylesheet">
</head>
<body>

<div class="bg-slideshow-container">
    <div class="bg-slide active" style="background-image: url('../images/campus.png');"></div>
    <div class="bg-slide" style="background-image: url('../images/students.png');"></div>
    <div class="bg-slide" style="background-image: url('../images/lab.png');"></div>
</div>

<div class="form-wizard-card">
    <h3>Senior One Admission Application</h3>

    <!-- Progress Steps -->
    <div class="progress-container mb-4">
        <div class="progress-step active" data-step="0">1</div>
        <div class="progress-step" data-step="1">2</div>
        <div class="progress-step" data-step="2">3</div>
        <div class="progress-line"></div>
        <div class="progress-line-fill"></div>
    </div>

    <form id="admissionForm" action="submit.php" method="POST" enctype="multipart/form-data">

        <!-- Step 1: Bio Data -->
        <fieldset class="active">
            <h5><i class="fa-solid fa-user"></i> Bio Data</h5>
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
                    <label class="form-label">Other Names</label>
                    <input class="form-control" name="othernames" placeholder="Other Names">
                </div>
            </div>

            <div class="row g-3 mt-2">
                <div class="col-md-6">
                    <label class="form-label">Sex</label>
                    <select class="form-select" name="sex" required>
                        <option value="">Select Sex</option>
                        <option>Male</option>
                        <option>Female</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="dob" class="form-label">Date of Birth</label>
                    <input type="date" class="form-control" name="dob" required>
                </div>
            </div>

            <div class="row g-3 mt-2">
                <div class="col-md-12">
                    <label class="form-label">Religion</label>
                    <select class="form-select" name="religion" required>
                        <option value="">Select Religion</option>
                        <option>Catholic</option>
                        <option>Anglican</option>
                        <option>Muslim</option>
                        <option>Others</option>
                    </select>
                </div>
            </div>

            <div class="mt-3">
                <label class="form-label">Upload Passport Photo</label>
                <input type="file" class="form-control" name="passport" required accept="image/*">
            </div>

            <div class="mt-4 text-end">
                <button type="button" class="btn btn-primary next-btn">Next <i class="fa-solid fa-arrow-right"></i></button>
            </div>
        </fieldset>

        <!-- Step 2: PLE Information -->
        <fieldset>
            <h5><i class="fa-solid fa-book-open"></i> PLE Information</h5>
            <div class="mb-3">
                <label class="form-label">Former Primary School</label>
                <input class="form-control" name="former_school" placeholder="Former Primary School" required>
            </div>
            <div class="mb-3">
                <label class="form-label">PLE Index Number</label>
                <input class="form-control" name="index_number" placeholder="PLE Index Number" required>
            </div>

            <?php
            $subjects = ['english'=>'English','mathematics'=>'Mathematics','sst'=>'Social Studies','science'=>'Science'];
            foreach($subjects as $key=>$label){
                echo "<div class='mb-3'><label class='form-label'>$label Grade</label>";
                echo "<select name='$key' class='form-select' required>";
                foreach(['D1','D2','C3','C4','C5','C6','P7','P8','F9'] as $g){
                    echo "<option>$g</option>";
                }
                echo "</select></div>";
            }
            ?>

            <div class="mb-3">
                <label class="form-label">Total Aggregates (Autocalculated)</label>
                <input type="number" class="form-control" name="aggregates" min="4" max="36" readonly required placeholder="Total Aggregates">
            </div>

            <div class="mb-3">
                <label class="form-label">Attach PLE Result Slip (PDF or Image)</label>
                <input type="file" class="form-control" name="result_slip" required accept=".pdf,image/*">
            </div>

            <div class="d-flex justify-content-between mt-4">
                <button type="button" class="btn btn-secondary prev-btn"><i class="fa-solid fa-arrow-left"></i> Previous</button>
                <button type="button" class="btn btn-primary next-btn">Next <i class="fa-solid fa-arrow-right"></i></button>
            </div>
        </fieldset>

        <!-- Step 3: Parent / Guardian -->
        <fieldset>
            <h5><i class="fa-solid fa-user-friends"></i> Parent / Guardian</h5>
            <div class="mb-3">
                <label class="form-label">Parent / Guardian Full Name</label>
                <input class="form-control" name="parent_name" placeholder="Parent / Guardian Full Name" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Phone Number / WhatsApp</label>
                <input class="form-control" name="parent_phone" placeholder="Phone Number" required>
            </div>
            <div class="mb-3">
                <label class="form-label">National ID Number (NIN)</label>
                <input class="form-control" name="parent_nin" placeholder="National ID Number (NIN)" required pattern="[A-Z0-9]{10,14}" title="Enter a valid NIN">
            </div>
            <div class="mb-3">
                <label class="form-label">Physical Address (Village / Town / District)</label>
                <textarea class="form-control" name="parent_address" placeholder="Physical Address (Village / Town / District)" rows="2" required></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-control" name="parent_email" placeholder="Email Address" required>
            </div>

            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" required id="confirmCheck">
                <label class="form-check-label" for="confirmCheck">I confirm the information provided is correct</label>
            </div>

            <div class="d-flex justify-content-between mt-4">
                <button type="button" class="btn btn-secondary prev-btn"><i class="fa-solid fa-arrow-left"></i> Previous</button>
                <button type="submit" class="btn btn-success">Submit Application <i class="fa-solid fa-paper-plane"></i></button>
            </div>
        </fieldset>

    </form>
</div>

<script>
const gradeValues = { "D1":1,"D2":2,"C3":3,"C4":4,"C5":5,"C6":6,"P7":7,"P8":8,"F9":9 };
const selects = document.querySelectorAll("select[name='english'], select[name='mathematics'], select[name='sst'], select[name='science']");
const aggregateInput = document.querySelector("input[name='aggregates']");
function calculateAggregates() {
    let total = 0;
    selects.forEach(sel => total += gradeValues[sel.value]);
    aggregateInput.value = total;
}
selects.forEach(sel => sel.addEventListener("change", calculateAggregates));
calculateAggregates();

// Multi-step form logic
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

        if (!inp.checkValidity()) {
            inp.reportValidity();
            return false;
        }

        // Custom validation: Parent Phone format
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

// Reset custom errors as user types/interacts
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

// Background slideshow cycle loop
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
