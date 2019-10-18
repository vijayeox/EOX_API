$('.form').find('input, textarea').on('keyup blur focus', function (e) {
  
  var $this = $(this),
      label = $this.prev('label');

	  if (e.type === 'keyup') {
			if ($this.val() === '') {
          label.removeClass('active highlight');
        } else {
          label.addClass('active highlight');
        }
    } else if (e.type === 'blur') {
    	if( $this.val() === '' ) {
    		label.removeClass('active highlight'); 
			} else {
		    label.removeClass('highlight');   
			}   
    } else if (e.type === 'focus') {
      
      if( $this.val() === '' ) {
    		label.removeClass('highlight'); 
			} 
      else if( $this.val() !== '' ) {
		    label.addClass('highlight');
			}
    }

});

$('.tab a').on('click', function (e) {
  
  e.preventDefault();
  
  $(this).parent().addClass('active');
  $(this).parent().siblings().removeClass('active');
  
  target = $(this).attr('href');

  $('.tab-content > div').not(target).hide();
  
  $(target).fadeIn(600);
  
});

document.addEventListener("DOMContentLoaded", function(){

  function autoLogin(data){
    localStorage.setItem("User",JSON.stringify({"key": data.username,"timestamp": new Date()}));
    localStorage.setItem("AUTH_token",JSON.stringify({"key": data.jwt,"timestamp": new Date()}));
    localStorage.setItem("REFRESH_token",JSON.stringify({"key": data.refresh_token,"timestamp": new Date()}));
    window.location.href = window.location.origin;
  }

Formio.createForm(document.getElementById('formio'), JSON.parse(formContent)).then(function(form) {
    // Prevent the submission from going to the form.io server.
    form.nosubmit = true;
    // Triggered when they click the submit button.
    form.on('submit', function(submission) {
      submission.data.app_id=appId;
      var response = fetch(baseUrl + "register", {
          body: JSON.stringify(submission),
          headers: {
            'content-type': 'application/json'
          },
          method: 'POST',
          mode: 'cors',
        }).then(response => {
          form.emit('submitDone', submission)
          return response.json();
        });
        response.then(res => {
          autoLogin(res.data);
        })
    });
    form.on("callDelegate", changed => {
      console.log(appId);
      var component = form.getComponent(event.target.id);
      if (component) {
        var properties = component.component.properties;
        if (properties) {
          if (properties["delegate"]) {
            $.ajax({
              type: "POST",
              async: false,
              url:
                baseUrl +
                "app/" +
                "a214bf2b-285b-4507-8eb7-4e89bfc3ecca" +
                "/delegate/" +
                properties["delegate"],
              data: changed,
              success: function(response) {
                if (response.data) {
                  form.submission = { data: response.data };
                  form.triggerChange();
                }
              }
            });
          }
        }
      }
    });
  });
});
