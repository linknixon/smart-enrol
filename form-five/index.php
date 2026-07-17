<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>S5 A-Level Online Application | St. Paul's Mutolere SS</title>
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
<h3>Senior Five (A-Level) Admission Application</h3>

<div class="progress-container">
    <div class="progress-step active">1</div>
    <div class="progress-step">2</div>
    <div class="progress-step">3</div>
    <div class="progress-line"></div>
    <div class="progress-line-fill"></div>
</div>

<form action="submit.php" method="POST" enctype="multipart/form-data" id="aLevelForm">

<!-- STEP 1: BIO DATA -->
<fieldset class="active">
<h5><i class="fa-solid fa-user"></i> Student Bio Data</h5>
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
        <input type="date" class="form-control" name="dob" id="dob" required>
    </div>
</div>

<div class="mt-3">
    <label class="form-label">Upload Passport Photo</label>
    <input type="file" class="form-control" name="passport" accept="image/*" required>
</div>

<div class="text-end mt-4">
    <button type="button" class="btn btn-primary next-btn">Next <i class="fa-solid fa-arrow-right"></i></button>
</div>
</fieldset>

<!-- STEP 2: UCE INFORMATION -->
<fieldset>
<h5><i class="fa-solid fa-graduation-cap"></i> UCE (O-Level) Information</h5>

<div class="mb-3">
    <label class="form-label">Former Secondary School</label>
    <input class="form-control" name="former_school" placeholder="Former Secondary School" required>
</div>
<div class="mb-3">
    <label class="form-label">UCE Index Number</label>
    <input class="form-control" name="uce_index" placeholder="UCE Index Number" required>
</div>

<div class="mb-3">
    <label class="form-label">Aggregates (8–54)</label>
    <input type="number" class="form-control" name="aggregates" placeholder="Aggregates (8–54)" min="8" max="54" required>
</div>

<div class="mb-3">
    <label class="form-label">Division Obtained</label>
    <select class="form-select" name="division" required>
        <option value="">Select Division</option>
        <option>Division 1</option>
        <option>Division 2</option>
        <option>Division 3</option>
        <option>Division 4</option>
    </select>
</div>

<div class="mb-3">
    <label class="form-label">Select A-Level Subject Combination</label>
    <select class="form-select" name="combination" required>
        <option value="">Select Combination</option>
        <optgroup label="Science Combinations">
            <option>PCM (Physics, Chemistry, Mathematics)</option>
            <option>PCB (Physics, Chemistry, Biology)</option>
            <option>BCM (Biology, Chemistry, Mathematics)</option>
            <option>PEM (Physics, Economics, Mathematics)</option>
            <option>MEG (Mathematics, Economics, Geography)</option>
            <option>HEM (History, Economics, Mathematics)</option>
        </optgroup>
        <optgroup label="Arts Combinations">
            <option>HEG (History, Economics, Geography)</option>
            <option>HEL (History, Economics, Literature)</option>
            <option>HLD (History, Literature, Divinity)</option>
            <option>LEG (Literature, Economics, Geography)</option>
            <option>GEA (Geography, Entrepreneurship, Art)</option>
        </optgroup>
    </select>
</div>

<div class="mb-3">
    <label class="form-label">Attach UCE Result Slip (PDF or Image)</label>
    <input type="file" class="form-control" name="result_slip" accept=".pdf,image/*" required>
</div>

<div class="d-flex justify-content-between mt-4">
    <button type="button" class="btn btn-secondary prev-btn"><i class="fa-solid fa-arrow-left"></i> Previous</button>
    <button type="button" class="btn btn-primary next-btn">Next <i class="fa-solid fa-arrow-right"></i></button>
</div>
</fieldset>

<!-- STEP 3: PARENT -->
<fieldset>
<h5><i class="fa-solid fa-user-friends"></i> Parent / Guardian Details</h5>
<div class="mb-3">
    <label class="form-label">Parent Full Name</label>
    <input class="form-control" name="parent_name" placeholder="Parent Full Name" required>
</div>
<div class="mb-3">
    <label class="form-label">Phone Number / WhatsApp</label>
    <input class="form-control" name="parent_phone" placeholder="Phone Number" required>
</div>
<div class="mb-3">
    <label class="form-label">National ID Number (NIN)</label>
    <input class="form-control" name="parent_nin" placeholder="National ID Number" required>
</div>
<div class="mb-3">
    <label class="form-label">Physical Address</label>
    <textarea class="form-control" name="parent_address" placeholder="Physical Address" required></textarea>
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
const fieldsets = document.querySelectorAll("fieldset");
const steps = document.querySelectorAll(".progress-step");
const lineFill = document.querySelector(".progress-line-fill");
let current = 0;

function showStep(index){
    fieldsets.forEach((fs,i)=> fs.classList.toggle("active", i===index));
    steps.forEach((s,i)=> s.classList.toggle("active", i<=index));
    lineFill.style.width = (index/(fieldsets.length-1))*100 + "%";
}

function validateStep(index){
    const inputs = fieldsets[index].querySelectorAll("input, select, textarea");
    for(let inp of inputs){
        inp.setCustomValidity("");

        if(!inp.checkValidity()){
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

document.querySelectorAll(".next-btn").forEach(btn=>{
    btn.addEventListener("click", ()=>{
        if(validateStep(current) && current < fieldsets.length-1){ current++; showStep(current); }
    });
});

document.querySelectorAll(".prev-btn").forEach(btn=>{
    btn.addEventListener("click", ()=>{
        if(current > 0){ current--; showStep(current); }
    });
});

// Handle form submission and prevent default if invalid
document.getElementById('aLevelForm').addEventListener('submit', function(e){
    if(!validateStep(current)){
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
