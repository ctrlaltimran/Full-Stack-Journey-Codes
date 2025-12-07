<?php
/**
 * Plugin Name: Refinance Quiz
 * Plugin URI: 
 * Description: Refinance quiz plugin.
 * Version: 1.0
 * Author: 
 * Author URI: 
 */

add_shortcode('refi_form', function() {

    ob_start();

    /* -------------------------
       IF FORM WAS SUBMITTED
    --------------------------*/
    if ($_SERVER['REQUEST_METHOD'] === 'POST' ) {
       
        $to = "syedimranmurtaza32@gmail.com";
        $subject = "New Refinance Form Submission";
        $message = "A new refinance lead has submitted their details:\n\n";

        foreach ($_POST as $key => $value) {
            if ($key === "final_submit") continue;
            $message .= ucfirst(str_replace("_", " ", $key)) . ": " . sanitize_text_field($value) . "\n";
        }

         wp_mail($to, $subject, $message, ["Content-Type: text/plain; charset=UTF-8"]);
	
        echo "<div style='background:#e2ffe2;padding:15px;border-left:5px solid #29a329;margin-bottom:15px;'>
                <strong>Thank you!</strong> Your form was successfully submitted.
              </div>";

        return ob_get_clean();
		
    }

?>
<style>
.step { display:none; max-width:var(--maxw); width:100%; }
.step.active { display:block; }

.step h1{
    font-size:34px;
    font-weight:800;
    margin-bottom:10px;
}

.step h3{
    font-size:20px;
    margin-bottom:25px;
    color:#444;
}

/* STEP 1 CARDS */
.options-row{
    display:flex;
    gap:40px;
    margin-bottom:40px;
}

.option-card{
    width:260px;
    padding:25px;
    border:2px solid #dcdcdc;
    text-align:center;
    border-radius:12px;
    background:#fff;
    cursor:pointer;
    transition:.25s ease;
}

.option-card img{
    width:160px;
    margin-bottom:15px;
}

.option-card:hover{
    border-color:black;
    transform:translateY(-5px);
}

.option-card.selected{
    border-color:black;
    box-shadow:0 3px 12px rgba(0,0,0,0.2);
}

/* BUTTONS */
.next-btn, .prev-btn{
    padding:12px 28px;
    border-radius:40px;
    font-size:18px;
    cursor:pointer;
    border:none;
}

.next-btn{
    background:black;
    color:#fff;
	margin: 12px -8px;
}

.prev-btn{
    background:#fff;
    border:2px solid #000;
    margin-bottom:20px;
}

/* STEP 2 RADIO OPTIONS */
 h1{
    font-size: 32px;
    font-weight: 800;
    margin-bottom: 25px;
}

.goal-option{
    margin-bottom: 18px;
}

.goal{
    width: 100%;
    border: 2px solid #000;
    border-radius: 40px;
	padding: 14px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    cursor: pointer;
    transition: 0.2s ease;
}

.goal:hover{
    background: black;
	  color:white;
}

.goal input{
    display: none;
}
.goal input[type="radio"]:checked + .text {
    background: black;
    color: white;
	padding: 14px 20px;
    border-radius: 40px;
    width: 100%;
}


/* checked */


.text{
    font-size: 16px;
    font-weight: 500;
}
.title {
    font-size: 32px;
    font-weight: 700;
    font-family: inherit;
}

.subtitle {
    font-size: 15px;
    color: #555;
    margin-top: -13px;
    margin-bottom: 40px;
}

.input-container {
    position: relative;
    width: 100%;
    padding-top: 5px;
    margin-top: 40px; 
}

.input-container::after {
    position: absolute;
    left: 0;
    bottom: -8px; 
    width: 100%;
    height: 2px;
    background: #ddd;
}

.dollar-placeholder {
    position: absolute;
    left: 0;
    top: 5px;
    font-size: 18px;
    color: #ccc;
    pointer-events: none;
    transition: 0.1s;
}

#home_value {
    width: 100%;
    border: none;
    outline: none;
    font-size: 18px;
    background: transparent;
    padding: 0;
}
select#interest_rate {
    border: none;
	  cursor:pointer;
}
.vet-options {
  display: flex;
  gap: 25px;
  margin-top: 25px;
}

.vet-box {
  width: 150px;
  padding: 20px;
  border: 2px solid #ccc;
  border-radius: 8px;
  cursor: pointer;
  text-align: center;
  transition: 0.2s;
}

.vet-box:hover {
  border-color: #000;
}

.vet-box.selected {
  border-color: #000;
  background: #f3f3f3;
}

.icon-circle {
  width: 90px;
  height: 90px;
  border: 4px solid #cfcfcf;
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  margin: auto;
}

.checkmark,
.cross {
  font-size: 48px;
  color: #bfbfbf;
  font-weight: bold;
}

.vet-box p {
  margin-top: 15px;
  font-size: 18px;
}

.consent-wrap {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 14px;
    line-height: 1.4;
    margin-bottom: 20px;
}

.phone-field input {
    width: 100%;
    padding: 14px;
    font-size: 18px;
    border: none;
    border-bottom: 2px solid black;
    background: #e9f0ff; /* light blue background */
    outline: none;
    transition: box-shadow 0.2s ease;
}

.phone-field input:focus {
    box-shadow: 0 0 0 3px rgba(100, 150, 255, 0.5);
}

</style>

<script>
let currentStep = 1;
const totalSteps = 17;

function showStep(step){
    for(let i=1; i<=totalSteps; i++){
        document.getElementById('step'+i).classList.remove('active');
    }
    document.getElementById('step'+step).classList.add('active');
}

function goNext(){
    // validate before moving forward
    if (!validateStep(currentStep)) {
        alert("Please complete this field before continuing.");
        return;
    }

    if(currentStep < totalSteps){
        currentStep++;
        showStep(currentStep);
        updateNextButton();
    }
}


function goPrev(){
    if(currentStep > 1){
        currentStep--;
        showStep(currentStep);
    }
}

function selectOption(value, element){
    document.querySelectorAll('#step1 .option-card').forEach(c=>c.classList.remove('selected'));
    element.classList.add('selected');
    document.getElementById('home_type').value = value;
}

function numberOnly(input) {
    // Remove all non-numeric characters except $
    let val = input.value.replace(/[^0-9]/g, '');
    
    // Add $ at start if not present
    if(val.length > 0){
        input.value = '$' + val;
    } else {
        input.value = '';
    }

    // Hide placeholder when typing
    const placeholder = document.getElementById("placeholder");
    placeholder.style.opacity = val.length > 0 ? "0" : "1";
}

function selectVet(value, box) {
    // remove selected from both
    document.querySelectorAll('.vet-box').forEach(b => b.classList.remove('selected'));

    // add to clicked
    box.classList.add('selected');

    // store value
    document.getElementById('veteran_status').value = value;
}

const slider = document.getElementById("cashSlider");
const valueBubble = document.getElementById("sliderValue");

function updateBubble() {
    const val = slider.value;
    const max = slider.max;

    // % position across slider
    const percent = (val / max) * 100;

    // update bubble text
    valueBubble.textContent = val;

    // update bubble position
    valueBubble.style.left = `calc(${percent}% - 30px)`;
}

slider.addEventListener("input", updateBubble);

// Run on page load
updateBubble();

function formatPhone(input) {
    let val = input.value.replace(/\D/g, "").substring(0, 10);

    let part1 = val.substring(0, 3);
    let part2 = val.substring(3, 6);
    let part3 = val.substring(6, 10);

    if (val.length > 6) {
        input.value = `(${part1}) ${part2}-${part3}`;
    } else if (val.length > 3) {
        input.value = `(${part1}) ${part2}`;
    } else if (val.length > 0) {
        input.value = `(${part1}`;
    }
}

function updateNextButton() {
    const nextBtn = document.querySelector('.next-btn');
    if (currentStep === totalSteps) {
        nextBtn.style.display = "none";   // Hide on last step
    } else {
        nextBtn.style.display = "block";  // Show on all other steps
    }
}
updateNextButton();

function validateStep(step) {
    switch(step) {
        case 1:
            if (!document.getElementById('home_type').value) return false;
            break;
        case 2:
            if (!document.querySelector('input[name="goal"]:checked')) return false;
            break;
        case 3:
            if (!document.querySelector('input[name="loan_type"]:checked')) return false;
            break;
        case 4:
            if (!document.querySelector('input[name="home_value"]').value) return false;
            break;
        case 5:
            if (!document.querySelector('input[name="current_loan_balance"]').value) return false;
            break;
        case 6:
            if (!document.querySelector('select[name="interest_rate"]').value) return false;
            break;
        case 7:
            if (!document.querySelector('input[name="veteran_status"]:checked')) return false;
            break;
        case 8:
            if (!document.querySelector('input[name="employment_status"]:checked')) return false;
            break;
        case 9:
            if (!document.querySelector('input[name="household_income"]').value) return false;
            break;
        case 10:
            if (!document.querySelector('input[name="bankruptcy_status"]:checked')) return false;
            break;
        case 11:
            if (!document.querySelector('input[name="cash_out_amount"]').value) return false;
            break;
        case 12:
            if (!document.querySelector('input[name="credit_profile"]:checked')) return false;
            break;
        case 13:
            if (!document.querySelector('input[name="full_name"]').value.trim()) return false;
            break;
        case 14:
            let email = document.querySelector('input[name="email_address"]').value;
            if (!email || !email.includes('@') || !email.includes('.')) return false;
            break;
        case 15:
            let phone = document.querySelector('input[name="phone_number"]').value;
            if (!phone || phone.length < 14) return false;
            break;
        case 17:
            return true; // final submit allowed
    }
    return true;
}



</script>

<form method="POST">


<!-- === STEP 1 === -->
<div class="step active" id="step1">
  <h1>Access Cash. Reduce Payments. Consolidate Debt.<br>Let’s explore if refinancing is the right move for you.</h1>
  <h3>What type of home do you own?</h3>
  <div class="options-row">
      <div class="option-card" onclick="selectOption('Single-Family', this)">
            <img src="https://denalifundinggroup.com/wp-content/uploads/2024/12/WhatsApp_Image_2024-12-13_at_4.23.00_AM-removebg-preview.png" alt="">
          <p>Single-Family</p>
      </div>
      <div class="option-card" onclick="selectOption('Condominium', this)">
           <img src="https://denalifundinggroup.com/wp-content/uploads/2024/12/WhatsApp_Image_2024-12-13_at_4.23.01_AM-removebg-preview.png" alt="">
          <p>Condominium</p>
      </div>
      <div class="option-card" onclick="selectOption('Townhome', this)">
          <img src="https://denalifundinggroup.com/wp-content/uploads/2024/12/WhatsApp_Image_2024-12-13_at_4.23.00_AM__1_-removebg-preview.png" alt="">
          <p>Townhome</p>
      </div>
  </div>
  <input type="hidden" id="home_type" name="home_type">
  <button type="button" class="next-btn" onclick="goNext()">Next →</button>
</div>

<!-- === STEP 2 === -->
<div class="step" id="step2">
  <button type="button" class="prev-btn" onclick="goPrev()">← Previous</button>
  <h1>What’s your goal for refinancing your home loan?</h1>
  <div class="goal-option">
      <label class="goal">
          <input type="radio" name="goal" value="cash">
          <span class="text">Access cash from your home</span>
      </label>
  </div>
  <div class="goal-option">
      <label class="goal">
          <input type="radio" name="goal" value="consolidate">
          <span class="text">Consolidate and pay off debts</span>
      </label>
  </div>
  <div class="goal-option">
      <label class="goal">
          <input type="radio" name="goal" value="reduce">
          <span class="text">Reduce your monthly payments</span>
      </label>
  </div>
  <div class="goal-option">
      <label class="goal">
          <input type="radio" name="goal" value="explore">
          <span class="text">Explore other options</span>
      </label>
  </div>
  <button type="button" class="next-btn" onclick="goNext()">Next →</button>
</div>

<!-- === STEP 3 === -->
<div class="step" id="step3">
  <button type="button" class="prev-btn" onclick="goPrev()">← Previous</button>
  <h1>What type of home loan are you refinancing?</h1>
  <div class="goal-option">
      <label class="goal">
          <input type="radio" name="loan_type" value="conventional">
          <span class="text">Conventional Loan</span>
      </label>
  </div>
  <div class="goal-option">
      <label class="goal">
          <input type="radio" name="loan_type" value="va">
          <span class="text">VA Loan</span>
      </label>
  </div>
  <div class="goal-option">
      <label class="goal">
          <input type="radio" name="loan_type" value="usda">
          <span class="text">USDA Loan</span>
      </label>
  </div>
  <div class="goal-option">
      <label class="goal">
          <input type="radio" name="loan_type" value="fha">
          <span class="text">FHA Loan</span>
      </label>
  </div>
  <div class="goal-option">
      <label class="goal">
          <input type="radio" name="loan_type" value="not_sure">
          <span class="text">Not sure yet</span>
      </label>
  </div>
  <button type="button" class="next-btn" onclick="goNext()">Next →</button>
</div>

<!-- === STEP 4 === -->
<div class="step" id="step4">
  <button type="button" class="prev-btn" onclick="goPrev()">← Previous</button>
  <h1>What's the value of your home?</h1>
  <div class="input-container">
      <input type="text" name="home_value" placeholder="$0" oninput="numberOnly(this)">
  </div>
  <button type="button" class="next-btn" onclick="goNext()">Next →</button>
</div>

<!-- === STEP 5 === -->
<div class="step" id="step5">
  <button type="button" class="prev-btn" onclick="goPrev()">← Previous</button>
  <h1>What's the remaining balance of your current loan?</h1>
  <div class="input-container">
      <input type="text" name="current_loan_balance" placeholder="$0" oninput="numberOnly(this)">
  </div>
  <button type="button" class="next-btn" onclick="goNext()">Next →</button>
</div>

<!-- === STEP 6 === -->
<div class="step" id="step6">
  <button type="button" class="prev-btn" onclick="goPrev()">← Previous</button>
  <h1>What's your current mortgage interest rate?</h1>
  <select name="interest_rate">
      <option value="">Select rate</option>
      <option value="2%">2%</option>
      <option value="3%">3%</option>
      <option value="4%">4%</option>
      <option value="5%">5%</option>
      <option value="6%">6%</option>
      <option value="7%">7%</option>
      <option value="8%">8%</option>
      <option value="9%">9%</option>
      <option value="10%">10%</option>
      <option value="More than 10%">More than 10%</option>
  </select>
  <button type="button" class="next-btn" onclick="goNext()">Next →</button>
</div>

<!-- === STEP 7 === -->
<div class="step" id="step7">
  <button type="button" class="prev-btn" onclick="goPrev()">← Previous</button>
  <h1>Are you a veteran?</h1>
  <div class="goal-option">
      <label class="goal">
          <input type="radio" name="veteran_status" value="yes">
          <span class="text">Yes</span>
      </label>
  </div>
  <div class="goal-option">
      <label class="goal">
          <input type="radio" name="veteran_status" value="no">
          <span class="text">No</span>
      </label>
  </div>
  <button type="button" class="next-btn" onclick="goNext()">Next →</button>
</div>

<!-- === STEP 8 === -->
<div class="step" id="step8">
  <button type="button" class="prev-btn" onclick="goPrev()">← Previous</button>
  <h1>Employment status</h1>
  <div class="goal-option">
      <label class="goal">
          <input type="radio" name="employment_status" value="employed">
          <span class="text">Employed</span>
      </label>
  </div>
  <div class="goal-option">
      <label class="goal">
          <input type="radio" name="employment_status" value="self_employed">
          <span class="text">Self-Employed</span>
      </label>
  </div>
  <div class="goal-option">
      <label class="goal">
          <input type="radio" name="employment_status" value="unemployed">
          <span class="text">Unemployed</span>
      </label>
  </div>
  <button type="button" class="next-btn" onclick="goNext()">Next →</button>
</div>

<!-- === STEP 9 === -->
<div class="step" id="step9">
  <button type="button" class="prev-btn" onclick="goPrev()">← Previous</button>
  <h1>Household Income</h1>
  <div class="input-container">
      <input type="text" name="household_income" placeholder="$0" oninput="numberOnly(this)">
  </div>
  <button type="button" class="next-btn" onclick="goNext()">Next →</button>
</div>

<!-- === STEP 10 === -->
<div class="step" id="step10">
  <button type="button" class="prev-btn" onclick="goPrev()">← Previous</button>
  <h1>Have you ever filed bankruptcy?</h1>
  <div class="goal-option">
      <label class="goal">
          <input type="radio" name="bankruptcy_status" value="yes">
          <span class="text">Yes</span>
      </label>
  </div>
  <div class="goal-option">
      <label class="goal">
          <input type="radio" name="bankruptcy_status" value="no">
          <span class="text">No</span>
      </label>
  </div>
  <button type="button" class="next-btn" onclick="goNext()">Next →</button>
</div>

<!-- === STEP 11 === -->
<div class="step" id="step11">
  <button type="button" class="prev-btn" onclick="goPrev()">← Previous</button>
  <h1>How much cash-out are you seeking?</h1>
  <div class="input-container">
      <input type="text" name="cash_out_amount" placeholder="$0" oninput="numberOnly(this)">
  </div>
  <button type="button" class="next-btn" onclick="goNext()">Next →</button>
</div>

<!-- === STEP 12 === -->
<div class="step" id="step12">
  <button type="button" class="prev-btn" onclick="goPrev()">← Previous</button>
  <h1>Your Credit Profile</h1>
  <div class="goal-option">
      <label class="goal">
          <input type="radio" name="credit_profile" value="720+">
          <span class="text">720+</span>
      </label>
  </div>
  <div class="goal-option">
      <label class="goal">
          <input type="radio" name="credit_profile" value="660-719">
          <span class="text">660-719</span>
      </label>
  </div>
  <div class="goal-option">
      <label class="goal">
          <input type="radio" name="credit_profile" value="620-659">
          <span class="text">620-659</span>
      </label>
  </div>
  <div class="goal-option">
      <label class="goal">
          <input type="radio" name="credit_profile" value="580-619">
          <span class="text">580-619</span>
      </label>
  </div>
  <div class="goal-option">
      <label class="goal">
          <input type="radio" name="credit_profile" value="579 or below">
          <span class="text">579 or below</span>
      </label>
  </div>
  <button type="button" class="next-btn" onclick="goNext()">Next →</button>
</div>

<!-- === STEP 13 === -->
<div class="step" id="step13">
  <button type="button" class="prev-btn" onclick="goPrev()">← Previous</button>
  <h1>What's your full name?</h1>
  <div class="input-container">
      <input type="text" name="full_name" placeholder="Full Name">
  </div>
  <button type="button" class="next-btn" onclick="goNext()">Next →</button>
</div>

<!-- === STEP 14 === -->
<div class="step" id="step14">
  <button type="button" class="prev-btn" onclick="goPrev()">← Previous</button>
  <h1>Email Address</h1>
  <div class="input-container">
      <input type="email" name="email_address" placeholder="Email Address">
  </div>
  <button type="button" class="next-btn" onclick="goNext()">Next →</button>
</div>

<!-- === STEP 15 === -->
<div class="step" id="step15">
  <button type="button" class="prev-btn" onclick="goPrev()">← Previous</button>
  <h1>Phone Number</h1>
  <div class="phone-field">
      <input type="text" name="phone_number" placeholder="(555) 555-5555" oninput="formatPhone(this)">
  </div>
  <label class="consent-wrap">
      <input type="checkbox" checked>
      <span>You give explicit consent to receive phone calls, emails, and texts from our representatives. Standard rates may apply. You may opt out anytime.</span>
  </label>
  <button type="button" class="next-btn" onclick="goNext()">Next →</button>
</div>

<!-- === STEP 16 === -->
<div class="step" id="step16">
  <button type="button" class="prev-btn" onclick="goPrev()">← Previous</button>
  <h1>Leave a message (optional)</h1>
  <div class="input-container">
      <textarea name="message" placeholder="Your message here"></textarea>
  </div>
  <button type="button" class="next-btn" onclick="goNext()">Next →</button>
</div>

<!-- === STEP 17 === -->
<div class="step" id="step17">
  <button type="button" class="prev-btn" onclick="goPrev()">← Previous</button>
  <h1>Submit your information</h1>
  <button type="submit" name="final_submit" class="submit-btn">Submit</button>
</div>

</form>

<?php
return ob_get_clean();
});
