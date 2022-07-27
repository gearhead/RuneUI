<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>RuneAudio - Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="msapplication-tap-highlight" content="no" />
    <link rel="stylesheet" href="assets/css/runeui.css">
    <link rel="shortcut icon" href="assets/img/favicon.ico">
    <style>
        #divlogin {
            text-align: center;
            margin-top: 30px;
        }
        #logo {
            width: 200px;
            margin-bottom: 20px;
        }
        input[type="password"] {
            display: inline;
            margin-top: 10px;
            width: 250px;
            text-align: center;
        }
        #login, #pwdset {
            margin-top: 20px;
        }
        #pwdchange {
            display: none;
        }
    </style>
</head>
<body>
<div id="divlogin">
    <img id="logo" class="logo" src="assets/img/runelogo.svg"><br>
    <input type="password" id="pwd" class="form-control osk-trigger input-lg"><br>
    <div id="pwdchange">
        existing<br>
        <input type="password" id="pwdnew" class="form-control osk-trigger input-lg"><br>
        new password<br>
        <a id="pwdset" class="btn btn-primary">Set</a>
    </div>
    <a id="login" class="btn btn-primary">Login</a>
</div>
<script src="assets/js/vendor/jquery-2.1.0.min.js"></script>

<script>
$(document).ready(function () {

$('#pwd').focus();

$('#logo').click(function() {
    $('#pwdchange, #login').toggle();
    $('#pwd').focus();
});
$('#login').click(loginSubmit);
$('#pwd').keypress(function (e) {
    if (e.which == 13) {
        loginSubmit();
        $(this).blur();
    }
});
function loginSubmit() {
    $.post(
        'loginverify.php',
        {'pwd': $('#pwd').val()},
        function (data) {
            if (data != 1) {
                alert('Wrong password');
            } else {
                window.location.href = '/';
            }
        }
    );
}

$('#pwdset').click(function() {
    $.post(
        'loginpwdsave.php',
        {'pwd': $('#pwd').val(), 'pwdnew': $('#pwdnew').val()},
        function (data) {
            if (data != 1) {
                alert('Wrong existing password');
                $('#pwd').val('').focus();
            } else {
                $('#logo').click();
                alert('Password changed');
                $('#pwd').val('').focus();
            }
        }
    );
});

});
</script>

</body>
</html>