/**
 *
 * @copyright  2019 Aggelos Bellos
 * @package    mod_attendance
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * 
 */

class PasswordManager {

    constructor () {

        this.sessionId = 0;
        this.password = "";

        this.renewPasswordLink = "tasks/password/reNewPassword.php?session=";
        this.renewPasswordTimer = ""; // interval to renew password
        this.renewPasswordEverySeconds = 15;

        this.passwordChangeTimerSelector = ".password-timer" // selector to password change timer
        this.passwordContainerSelector = ".student-password"; // selector to current password container

        this.qrCodeText = "";
        this.qrCodeInstance = ""; 
        this.qrCodeElement = ""; // html element to hold the qr code

    }
    // display qr code and start services
    start ( sessionId, qrCodeElement, password, interval = 0 ) {

        this.sessionId = sessionId;

        this.qrCodeElement = qrCodeElement;
        this.password = password;

        if(interval > 0) {
            this.renewPasswordEverySeconds = interval;
        }

        this.qrCodeSetUp();
        this.changeQRCode( password );
        this.startServices();

    }
    // this function is being called only once
    // to start the qr code instance
    qrCodeSetUp () {

        this.qrCodeInstance = new QRCode(this.qrCodeElement, {
            text: '',
            width: 328,
            height: 328,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });

    }

    changePassword ( password ) {
        
        this.password = password;
        this.changeQRCode( password );

    }
    // change the qr code
    changeQRCode ( text ) {

        this.qrCodeText = '/mod/attendance/attendance.php?qrpass=' + text + '&sessid=' + this.sessionId;
        // display new password
        document.querySelector(this.passwordContainerSelector).innerHTML = text;

        this.qrCodeInstance.clear(); // clear the code
        this.qrCodeInstance.makeCode(text); // make another code.

    }

    updateTimer ( counter ) {
        document.querySelector(this.passwordChangeTimerSelector).innerHTML = this.renewPasswordEverySeconds - counter;
    }
    // starts the intervals for the renew/delete password
    startServices () {

        let self = this;
        let counter = -1;

        this.renewPasswordTimer = setInterval(function() {

            counter += 1;
            self.updateTimer(counter);
                    
            if(counter == self.renewPasswordEverySeconds) {

                counter = -1;
                self.reNewPassword();

            }

        }, 1000);

    }
    // stops the intervals for the renew/delete password
    stopServices () {

        this.renewPasswordTimer = null;

    }
    // renew Password and return it
    reNewPassword () {

        fetch(this.renewPasswordLink + this.sessionId, 
            {
                headers: { 
                    'Content-Type': 'application/json; charset=utf-8' 
                }
            })
            .then(res => res.json())
            .then(response => {
                this.changePassword( response.new_password );
            })
            .catch(err => {
                // TODO: add alert for error
            });

    }

}