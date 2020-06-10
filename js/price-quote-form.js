// Loops through form questions, records responses, tracks score, and submits data via AJAX

jQuery(document).ready( function() {
    var formNumber, form, questions, scoreTotal, priceTotal, emailSent, stripeContainer, stripeButtons;
    formNumber = 0;
    form = document.getElementById('price-quote-form');
    stripeContainer = document.getElementById('stripe-buttons');
    stripeButtons = stripeContainer.children;
    
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

        if (questions[formNumber].querySelector('input').type == 'email' && questions[formNumber].querySelector('input').value == '')  return;

        var ele = questions[formNumber].querySelector('input');
        var chk_status = ele.checkValidity();
        ele.reportValidity();
        if (!chk_status) {
            return;
        }

        var inputType = questions[formNumber].querySelector('input').type;
        var submittedVal;
        var submittedVals = [];
        if (inputType === "text") {
            submittedVal = questions[formNumber].querySelector('input').value;
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
                formResponses[name] = ['text', field.value];
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

        if (questions[formNumber + 1]) {
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
            formResponses['postID'] = postID;
            questions['lastForm'] = 'false';

            questions[formNumber].style.display = 'none';
            questions[formNumber + 1].style.display = 'block';
            formNumber = formNumber + 1;

            if (!questions[formNumber + 1]) {
                questions['lastForm'] = 'true';
                form.remove();
                document.querySelector('.last-form').style.display = 'block';
                document.getElementById('total-cost').innerHTML = '<strong>$' + priceTotal + '.00</strong>';
                document.getElementById('total-description').innerHTML = '<strong>Your custom home maintenance would cost $' + priceTotal + '.00 per month. Pay now and schedule your 1st maintenance visit.</strong>';
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