import 'jquery/dist/jquery'
import 'bootstrap/dist/js/bootstrap.bundle'
import 'bootstrap/dist/css/bootstrap.css'

// Alert dismissal
$(document).ready(function () {
    $(".alert").fadeTo(3500, 500).slideUp(500, function () {
        $(".alert").slideUp(500);
    });
});