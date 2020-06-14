// Loops through form questions, records responses, tracks score, and submits data via AJAX

jQuery(document).ready( function() {
    var formNumber, form, questions, scoreTotal, priceTotal, emailSent, stripeContainer, stripeButtons, zipCodeInArea;
    formNumber = 0;
    form = document.getElementById('price-quote-form');
    stripeContainer = document.getElementById('stripe-buttons');
    stripeButtons = stripeContainer.children;
    zipCodeInArea = false;
    
    questions = form.children;
    scoreTotal = 0;
    priceTotal = 0;
    emailSent = 'false';

    for (i = 0; i < stripeButtons.length; i++) {
        stripeButtons[i].style.display = "none";
    }

    // Process scoring system
    var scoring = document.getElementById('form-outer-div').dataset.scoring;
    var postID = document.getElementById('form-outer-div').dataset.id;
    postID = parseInt(postID);
    scoring = scoring.split('\n');
    var scoringRules = [];
    scoring.forEach(function(line) {
        if (line.includes("|")) {
            line = line.split(" | ");
            if (line[0].includes(":")) {
                line[0] = line[0].split(" : ");
            } else {
                line[0] = line[0].split(" ");
            }
            scoringRules.push([line[0], line[1]]);
        }
    });

    // Make sure only first question is visible
    questions[0].style.display = "block";
    for (i = 1; i < questions.length; i++) {
        questions[i].style.display = "none";
    }

    document.addEventListener('click', function (event) {

        if (!event.target.matches('.form-button')) return;

        if (!questions[formNumber].querySelector('input')) return;

        // Validate email, phone number and zip code fields
        var ele = questions[formNumber].querySelector('input');
        var chk_status = ele.checkValidity();
        ele.reportValidity();
        if (!chk_status) {
            return;
        }

        // Check if entered zip code is within service range
        if (questions[formNumber].querySelector('.form-question').textContent.includes('zip code')) {
            var zipcodeInput, zipCode, zipScoring, zipCodes;
            zipcodeInput = questions[formNumber].querySelector('input');
            zipCode = zipcodeInput.value;
            zipScoring = JSON.parse(questions[formNumber].dataset.score);
            zipCodes = [];
            zipScoring.forEach(function(values) {
                values[1].forEach(function(value) {
                    zipCodes.push(value);
                });
            });

            if (zipCodes.includes(zipCode)) {
                zipCodeInArea = true;
            }
        }

        // Process user input
        var inputType = questions[formNumber].querySelector('input').type;
        var submittedVal;
        var submittedVals = [];
        if (inputType === "text") {
            submittedVal = questions[formNumber].querySelector('input').value;
            submittedVal = submittedVal.replace(/[^A-Za-z0-9@\.\' _-]+/g, '');
        } else {
            options = questions[formNumber].getElementsByTagName('input');
            for (var i = 0; i < options.length; i++) {
                if (options[i].checked) {
                    submittedVals.push(options[i].value);
                }
            }
        }
        var forms = document.getElementById('price-quote-form').getElementsByTagName('input');
        var formResponses = {};
        var formTitle = document.getElementById('form-outer-div').dataset.title.toLowerCase().replace(' ', '-');
        formResponses['formTitle'] = formTitle;
        formResponses['scoreTotal'] = scoreTotal;
        formResponses['priceTotal'] = priceTotal;
        for (var i = 0; i < forms.length; i++) {
            var field = forms[i];
            var type = field.type;
            var name = field.name;
            if (type === 'text' || type === 'email') {
                var value = field.value;
                value = value.replace(/[^A-Za-z0-9@\.\' _-]+/g, '');
                formResponses[name] = ['text', value];
            } else if (type === 'radio' || type === "checkbox") {
                if (field.checked) {
                    if (formResponses[name]) {
                        formResponses[name].push(field.value);
                    } else {
                        formResponses[name] = ['array', field.value];
                    }
                }
            }
        }
        var currentForms = questions[formNumber].getElementsByTagName('input');
        var type = currentForms[0].type;
        if ((type === 'radio' || type === "checkbox") && (questions[formNumber] != questions[questions.length - 1])) {
            var isChecked = false;
            for (var i = 0; i < currentForms.length; i++) {
                var field = currentForms[i];
                if (field.checked) {
                    isChecked = true;
                }
            }
            if (!isChecked) {
                var errorMessage = "This field is required";
                var currentForm = currentForms[currentForms.length - 1];
                currentForm.setCustomValidity(errorMessage);
                currentForm.reportValidity();
                return;
            }
        }

        // Determine score, update score and price totals
        if (questions[formNumber + 1] != null) {
            var currentScoring = JSON.parse(questions[formNumber].dataset.score);
            currentScoring.forEach(function(values) {
                if (values[0] === "range") {
                    var lowerBound = parseInt(values[1][0]);
                    var upperBound = parseInt(values[1][1]);
                    var score = parseInt(values[2]);
                    if (parseInt(submittedVal) >= lowerBound && parseInt(submittedVal) < upperBound) {
                        scoreTotal = scoreTotal + score;
                    }
                } else if (values[0] === "exact") {
                    if (values[1].includes(submittedVal)) {
                        scoreTotal = scoreTotal + parseInt(values[2]);
                    }
                } else {
                    if (submittedVals.includes(values[1])) {
                        scoreTotal = scoreTotal + parseInt(values[2]);
                    }
                }
            });
 
            for (var i = 0; i < scoringRules.length; i++) {
                var lowerBound = parseInt(scoringRules[i][0][0]);
                var upperBound = parseInt(scoringRules[i][0][1]);
                var price = parseInt(scoringRules[i][1]);
                if (scoreTotal >= lowerBound && (scoreTotal < upperBound || i == scoringRules.length - 1)) {
                    priceTotal = price;
                }
            }

            formResponses['scoreTotal'] = scoreTotal;
            formResponses['priceTotal'] = priceTotal;
            formResponses['emailSent'] = emailSent;
            formResponses['lastForm'] = 'false';

            questions[formNumber].style.display = 'none';
            questions[formNumber + 1].style.display = 'block';
        } else {
            // Show final slide. Display price total and Stripe payment button.
            questions[formNumber].style.display = 'none';
            formResponses['lastForm'] = 'true';
            form.remove();
            document.querySelector('.last-form').style.display = 'block';
            var finalDescription = document.getElementById('total-description');
            var scoreLength = scoringRules.length;
            var maxScore = parseInt(scoringRules[scoreLength - 1][0][1]);
            var description = '';
            if ( scoreTotal >= maxScore || !zipCodeInArea) {
                formResponses['priceTotal'] = 'custom';
                if ( scoreTotal >= maxScore && zipCodeInArea) {
                    description += finalDescription.dataset.maxscore;
                } else if (!zipCodeInArea) {
                    description += finalDescription.dataset.zip;
                }
                finalDescription.innerHTML = '<strong>' + description + '</strong>';
            } else {
                document.getElementById('total-cost').innerHTML = '<strong>$' + priceTotal + '.00</strong>';
                description = finalDescription.dataset.text;
                description = description.replace('%s', '$' + priceTotal + '.00');
                finalDescription.innerHTML = '<strong>' + description + '</strong>';
                stripeContainer.style.display = 'block';
                for (var i = 0; i < scoringRules.length; i++) {
                    var lowerBound = parseInt(scoringRules[i][0][0]);
                    var upperBound = parseInt(scoringRules[i][0][1]);
                    var price = parseInt(scoringRules[i][1]);
                    if (scoreTotal >= lowerBound && (scoreTotal < upperBound || i == scoringRules.length - 1)) {
                        stripeButtons[i].style.display = 'block';
                    }
                }
            }
        }
        formNumber = formNumber + 1;
        formResponses['postID'] = postID;

        // Submit data via AJAX
        $.ajax({
            url: form_object.ajax_url,
            type:"POST",
            data: {
                action:'set_form',
                formResponse : JSON.stringify(formResponses),
           },   
           success: function(response) {
              console.log('success');
           }, 
           error: function(data) {
            console.log('error');     
           }
         });

        emailSent = 'true';
        
    
    }, false);

})