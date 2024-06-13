(() => {
  window.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('.nine3-migrate');
    const stopButton = document.querySelectorAll('.stop-import');
    const progressBar = document.querySelector('.progress-bar');
    const progressBarLoader = document.querySelector('.progress-bar__loader');
    const progressBarStep = document.querySelector('.progress-bar__step');
    const progressBarTotal = document.querySelector('.progress-bar__total');
    let abort = false;

    if (!forms[0] || stopButton === null) {
      return;
    }

    const triggerMysqlMigration = function (form, offset = 0) {
      if (abort === true) {
        return;
      }

      const formData = new FormData(form);
      const action = formData.get('action');
      const dataObject = {};
      formData.forEach((value, key) => {
        if (value) {
          if (value === 'plain') {
            const keyStr = key.replace('[type]', '[key]');
            const keyValue = formData.get(keyStr);
            if (keyValue) {
              dataObject[keyStr] = keyValue;
              dataObject[key] = value;
            }
          } else {
            dataObject[key] = value;
          }
        }
      });
      const data = JSON.stringify(dataObject);
      
      // The XMLHttpRequest magic.
      const xhttp = new XMLHttpRequest();
      xhttp.onreadystatechange = function () {
        if (this.readyState == 4) {
          const response = JSON.parse(xhttp.response);
          const stepPercent = 100 / response.total;
          offset = response.offset;
          progressBar.classList.add('active');
          progressBarTotal.innerHTML = response.total;

          if (response.error || !response) {
            console.log('ERROR');
            console.log('Error message: ' + response.message);
            console.log('Response: ' + xhttp.response);
          } else if (response.complete === 1) {
            console.log('FINISHED');
            console.log(new Date());
            if (response.html) {
              progressBar.innerHTML += response.html;
            }
          } else {
            progressBarLoader.style.width = (offset * stepPercent) + '%';
            progressBarStep.innerHTML = offset;
            console.log(offset + ') ' + response.message + response.id);
            triggerMysqlMigration(form, offset);
          }
        }
      };

      xhttp.open('GET', `${ajaxurl}?action=${action}&offset=${offset}&data=${data}`);
      xhttp.send();
    };

    forms.forEach((form) => {
      const offsetInput = form.querySelector('input[name=offset]');
      const startPos = offsetInput.value;
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        abort = false;
        triggerMysqlMigration(form, startPos);
      });
    });

    stopButton.forEach((button) => {
      button.addEventListener('click', function (e) {
        e.preventDefault();
        abort = true;
      });
    });
  });
})();
