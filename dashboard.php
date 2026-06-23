<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Dealer Dashboard</title>

   <link href="css/orderbook_style.css" rel="stylesheet" /> 
    <?php include('header_css.php'); ?>


</head>

<body>
    <div class="main-wrapper" id="mainWrapper">
 
        <!-- SIDEBAR -->

        <?php include('sidebar.php'); ?>

        <!-- MAIN -->

        <?php include('dashboard_data.php'); ?>

        <!-- SCRIPT -->
    </div>
    <script>
        const sidebar = document.getElementById('sidebar');

        const mobileClose = document.getElementById('mobileClose');

        const mainWrapper = document.getElementById('mainWrapper');


        mobileClose.addEventListener('click', function() {

            sidebar.classList.remove('mobile-show');

        });
    </script>

</body>

</html>