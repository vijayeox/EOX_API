$(".form")
  .find("input, textarea")
  .on("keyup blur focus", function(e) {
    var $this = $(this),
      label = $this.prev("label");

    if (e.type === "keyup") {
      if ($this.val() === "") {
        label.removeClass("active highlight");
      } else {
        label.addClass("active highlight");
      }
    } else if (e.type === "blur") {
      if ($this.val() === "") {
        label.removeClass("active highlight");
      } else {
        label.removeClass("highlight");
      }
    } else if (e.type === "focus") {
      if ($this.val() === "") {
        label.removeClass("highlight");
      } else if ($this.val() !== "") {
        label.addClass("highlight");
      }
    }
  });
  
 $(document).on("click", ".tab-group .tab", function() {

    if($(this).text() == "Register"){
      $('.hub-registration').addClass('registerActivated')
    }else{
       $('.hub-registration').removeClass('registerActivated')
    }
 });
$(document).on("click", "button", function() {
   if($('.page-item.active .page-link').text() == "PERSONAL INFORMATION" ){
      $('.btn-wizard-nav-previous').eq(0).hide();
      $('.signUpWrapper').addClass('removeCurvedBg step-1');
       $('.signUpWrapper').addClass('personalInfo');
      $('.signUpWrapper').removeClass('step-2');

  }else if($('.page-item.active .page-link').text() == "COMPANY INFORMATION"){
       $('.signUpWrapper').addClass('removeCurvedBg step-2');
       $('.signUpWrapper').removeClass('step-1');
       $('nav').addClass('companyinfo');

  }else if($('.page-item.active .page-link').text() == 'INSURANCE INFORMATION'){
       $('.osjs-window-content').addClass('insuranceStep3');
        $('.osjs-window-content').removeClass('Onboarding');
  }
  else if($('.page-item.active .page-link').text() == 'FINANCIAL INFORMATION'){
       $('.osjs-window-content').addClass('financialStep4');
  }
   
  else{
      //$('.btn-wizard-nav-next').eq(0).hide();
      $('.btn-wizard-nav-cancel').eq(0).hide();
      $('.signUpWrapper').removeClass('personalInfo');
      $('nav').removeClass('companyinfo');
      $('.signUpWrapper').removeClass('removeCurvedBg')
      $('.osjs-window-content').removeClass('insuranceStep3');
      $('.osjs-window-content').removeClass('financialStep4');
      $('.signUpWrapper').removeClass('step-1');
      $('.signUpWrapper').removeClass('step-2');
    }
 });
$(document).on("click", ".page-link", function() {
   if($('.page-item.active .page-link').text() == "PERSONAL INFORMATION" ){
      $('.signUpWrapper').addClass('removeCurvedBg step-1');
      $('.signUpWrapper').removeClass('step-2');
    }else if($('.page-item.active .page-link').text() == 'COMPANY INFORMATION'){
       $('.signUpWrapper').addClass('removeCurvedBg step-2');
       $('.signUpWrapper').removeClass('step-1');
       $('nav').addClass('companyinfo');
       $('nav').addClass('active');

    }else{
       $('.btn-wizard-nav-next').eq(0).hide();
       $('.btn-wizard-nav-cancel').eq(0).hide();
      $('.osjs-window-content').removeClass('insuranceStep3');
      $('.osjs-window-content').removeClass('financialStep4');
     $('.signUpWrapper').removeClass('removeCurvedBg');
      $('.signUpWrapper').removeClass('step-1');
      $('.signUpWrapper').removeClass('step-2');
    }
 });

$(".tab a").on("click", function(e) {
  e.preventDefault();

  $(this)
    .parent()
    .addClass("active");
  $(this)
    .parent()
    .siblings()
    .removeClass("active");

  target = $(this).attr("href");

  $(".tab-content > div")
    .not(target)
    .hide();

  $(target).fadeIn(600);
});

document.addEventListener("DOMContentLoaded", function() {
  $("#username_field").keyup(function(e) {
    if (e.keyCode === 13) {
      loginAction();
    }
  });

  $("#password_field").keyup(function(e) {
    if (e.keyCode === 13) {
      loginAction();
    }
  });

  $(".loginButton").on("click", function(e) {
    loginAction();
  });


  $(".resetPassword").on("click", function(e) {
    forgotPassword();
  });

  function loginAction() {
    var username = document.getElementById("username_field").value;
    var password = document.getElementById("password_field").value;
    if (username && password) {
      const formData = new FormData();
      formData.append("username", username);
      formData.append("password", password);
      let response = fetch(baseUrl + "auth", {
        body: formData,
        method: "POST",
        mode: "cors"
      }).then(response => {
        return response.json();
      });
      response.then(res => {
        if (res.status == "success") {
          autoLogin(res.data);
        } else {

          Swal.fire({
            title: "Login Failed",
            html:
              '<div style="font-size: 17px">The Username and/or password is incorrect!  <br /> Please try again.</div>',
            icon: "error",
            confirmButtonText: "Forgot Password ?",
            showCancelButton: true
          }).then(result => {
            if (result.value == true) {
              forgotPassword();
            }
          });
          // document.getElementById("wrongPassword").style.display = "block";
        }
      });
    } else {
      Swal.fire({

        // position: "top-end",
        icon: "warning",
        title:
          "Please enter your " +
          (username ? "" : "username") +
          (!username && !password ? " and " : "") +
          (password ? "" : "password") +
          ".",
        showConfirmButton: false,
        timer: 220000
      });
    }
  }

  function forgotPassword() {
    Swal.fire({
      title: "Please enter your Username",
      input: "text",
      inputAttributes: {
        autocapitalize: "off"
      },
      inputValidator: value => {
        if (!value) {
          return "Please enter your Username";
        }
      },
      confirmButtonText: "Confirm",
      showCancelButton: true,
      showLoaderOnConfirm: true,
      preConfirm: login => {
        let formData = new FormData();
        formData.append("username", login);
        return fetch(baseUrl + "user/me/forgotpassword", {
          method: "post",
          body: formData
        })
          .then(response => {
            if (response.status == 417) {
              Swal.showValidationMessage(`We do not have an email on your account.<br/>Contact Us at support@eoxvantage.com`);
              return;
            }
            if (!response.ok) {
              throw new Error(response.statusText);
            }
            return response.json();
          })
          .catch(error => {
            Swal.showValidationMessage(`Request failed: Username  not found.`);
          });
      }
    }).then(result => {
      if (result.value.status == "success") {
        Swal.fire({
          position: "top-end",
          icon: "success",
          title: "Verification Mail has been sent to " + result.value.data.email,
          showConfirmButton: false,
          timer: 2100
        });
      } else {
        Swal.showValidationMessage(`Request failed: Username not found.`);
      }
    });
  }

  function autoLogin(data) {
    localStorage.clear();
    localStorage.setItem(
      "User",
      JSON.stringify({ key: data.username, timestamp: new Date() })
    );
    localStorage.setItem(
      "AUTH_token",
      JSON.stringify({ key: data.jwt, timestamp: new Date() })
    );
    localStorage.setItem(
      "REFRESH_token",
      JSON.stringify({ key: data.refresh_token, timestamp: new Date() })
    );
    window.location.href = window.location.origin;
  }

  Formio.createForm(
    document.getElementById("formio"),
    JSON.parse(formContent),
    { noAlerts: true }
  ).then(function(form) {
    // Prevent the submission from going to the form.io server.
    form.nosubmit = true;

    setTimeout(function() {}, 500);

    $( document ).ready(function() {
    $('.osjs-window-content').removeClass('insuranceStep3');
    $('.osjs-window-content').removeClass('financialStep4');
    $('.btn-wizard-nav-next').eq(0).hide();
    $('.btn-wizard-nav-cancel').eq(0).hide();
    });

$(window).on('load', function () {
console.log("form load");
  $('.osjs-window-content').removeClass('insuranceStep3');
  $('.osjs-window-content').removeClass('financialStep4');
  });


     form.on("submit", function(submission, next) {
      console.log("Submission start...............");
      
      submission.data.appId = appId;
      
      submission.data.preferences = JSON.stringify({timezone:moment.tz.guess()});
      console.log("data", submission.data); 
      var response = fetch(baseUrl + "register", {
        body: JSON.stringify(submission.data),
        headers: {
          "content-type": "application/json",
          "Accept": "application/json"
        },
        method: "POST",
        mode: "cors"
      }).then(response => {
        return response.json();
      });
      response.then(res => {
        console.log("test response then----");
        if (res.status == "success") {
          form.emit("submitDone", submission);
          setTimeout(() => {
            autoLogin(res.data);
          }, 500);
        } else {
          Swal.fire({
            icon: "error",
            title: "Submission Failed",
            text: res.message
          }).then(form.emit("error", submission));
        }
      });
    });
    form.on("customEvent", function (event) {
      console.log("check event ....");  
      console.log(event)
      var changed = event.data;
      if (event.type == "callDelegate") {
        var component = form.getComponent(event.target.id);
        if (component) {
          var component = event.component;
          if (properties) {
            if (properties["delegate"]) {
             $.ajax({
                type: "POST",
                Accept: 'application/json',
                'Content-Type': 'application/json',
                async: false,
                url:
                baseUrl +
                "app/" +
                appId +
                "/delegate/" +
                properties["delegate"],
                data: changed,
                success: function(response) {
                  if (response.data) {
                    form.setSubmission({ data: response.data });
                    form.triggerChange();
                  }
                }
              });
            }
          }
        }
      }
      if (event.type == "callCommands") {

        console.log(form.isValid(changed,true));
        console.log("data", event.data);
        console.log("changed data", changed);
        var component = event.component;
        if (component) {
          var properties = component.properties;
          if (properties) {
            if (properties["commands"]) {
              $.ajax({
                type: "POST",
                async: false,
                url:
                baseUrl +
                "app/" +
                appId +
                "/commands?" +
                $.param(JSON.parse(properties["commands"])),
                data: changed,
                success: function(response) {
                  if (response.data) {
                    console.log(response.data);
                      form.setSubmission({ data: response.data }).then(response2 => {
                        if (response.data.user_exists != '1') {
                          console.log("Inside NextPage");
                          form.nextPage();
                        }
                      });
                                       
                    console.log(form.submission)
                    form.triggerChange();
                  }
                }
              });
            }
          }
        }
      }
      
    });
    form.on("change", changed => {
      if (changed && changed.changed) {
        var component = changed.changed.component;
        var properties = component.properties;
        console.log(changed);
        if (properties) {
          if (properties["delegate"]) {
            $.ajax({
              type: "POST",
              async: true,
              url:
                baseUrl +
                "app/" +
                appId +
                "/delegate/" +
                properties["delegate"],
              data: changed.data,
              success: function(response) {
                if (response.data) {
                  form.submission = { data: response.data };
                  form.triggerChange();
                }
              }
            });
          }
          if(properties["clear_field"]){
            var targetComponent = form.getComponent(properties["clear_field"]);
            if (targetComponent) {
              targetComponent.setValue("");
            } 
          }
        }
      }
    });
  });
});
