<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Dealer - Complaint</title>

    <?php include('header_css.php'); ?>

    <link href="css/orderbook_style.css" rel="stylesheet" />

</head>

<body>


    <div class="main-wrapper" id="mainWrapper">
        <!-- TOPBAR -->

        <div class="topbar">

            <div class="topbar-left">
                <i class="bi bi-list toggle-btn" id="menuToggle"></i>
                Complaint
            </div>
            <?php include('topbar.php'); ?>
        </div>
        <!-- SIDEBAR -->

        <?php include('sidebar.php'); ?>

        <div class="content">

            <!-- PAGE HEADER -->

            <div class="page-header">

                <div>

                    <div class="page-subtitle">
                        Log and track complaints related to orders and deliveries.
                    </div>

                </div>

                <!-- RIGHT BUTTONS -->

                <div class="header-btn-group">

                    <!-- NEW -->

                    <button class="new-order-btn"
                        id="openOrderForm">

                        <i class="bi bi-plus-lg"></i>

                        New Complaint

                    </button>

                    <!-- CLOSE -->

                    <button class="close-form-btn"
                        id="closeOrderForm">

                        <i class="bi bi-x-lg"></i>

                        Cancel

                    </button>

                </div>

            </div>

            <!-- ORDER FORM -->

            <div class="order-form-card"
                id="orderFormCard">

                <!-- TOP -->

                <div class="order-form-top">

                    <div class="order-form-title">

                        <i class="bi bi-cart"></i>

                        New Complaint

                    </div>

                </div>

                <!-- FORM -->
                <div class="order-form-grid complaint-grid">

                    <!-- ORDER ID -->

                    <div class="form-group">

                        <label>
                            Order ID <span class="required">*</span>
                        </label>

                        <input type="text"
                            class="custom-input"
                            placeholder="e.g. ORD-2024-1847" />

                    </div>

                    <!-- CATEGORY -->

                    <div class="form-group">

                        <label>
                            Category <span class="required">*</span>
                        </label>

                        <select class="custom-input">

                            <option selected disabled>
                                Select category
                            </option>

                            <option>Delivery Delay</option>

                            <option>Damaged Product</option>

                            <option>Wrong Material</option>

                            <option>Invoice Issue</option>

                        </select>

                    </div>

                    <!-- DESCRIPTION -->

                    <div class="form-group full-width">

                        <label>
                            Description <span class="required">*</span>
                        </label>

                        <textarea class="custom-textarea"
                            placeholder="Describe the issue in detail..."></textarea>

                    </div>

                </div>

                <!-- BUTTONS -->

                <div class="complaint-btn-wrapper">

                    <button class="cancel-btn">
                        Cancel
                    </button>

                    <button class="submit-btn">
                        Submit Complaint
                    </button>

                </div>

            </div>

            <!-- TABLE CARD -->

            <div class="booking-card">

                <!-- HEADER -->

                <div class="booking-header">

                    <div class="booking-title">
                        Order History
                    </div>

                    <div class="booking-actions">

                        <!-- SEARCH -->

                        <div class="search-box">

                            <i class="bi bi-search"></i>

                            <input type="text"
                                placeholder="Search orders...">

                        </div>

                        <!-- STATUS -->

                        <select class="filter-select active-filter">

                            <option>All Status</option>
                            <option>Created</option>
                            <option>Pending</option>
                            <option>Dispatched</option>
                        </select>
                    </div>

                </div>

                <!-- TABLE -->

                <div class="table-responsive">

                    <table class="booking-table">

                        <thead>

                            <tr>

                                <th>Order ID</th>

                                <th>Date</th>

                                <th>Type</th>

                                <th>Items</th>

                                <th>Value</th>

                                <th>Status</th>

                            </tr>

                        </thead>

                        <tbody>

                            <tr>

                                <td>
                                    <a href="#"
                                        class="order-link">

                                        ORD-2024-1847

                                    </a>
                                </td>

                                <td>12 Apr 2024</td>

                                <td>List Price</td>

                                <td>5</td>

                                <td class="fw-semibold">
                                    ₹1,25,000
                                </td>

                                <td>
                                    <span class="status-badge created">
                                        Created
                                    </span>
                                </td>

                            </tr>

                            <tr>

                                <td>
                                    <a href="#"
                                        class="order-link">

                                        ORD-2024-1835

                                    </a>
                                </td>

                                <td>09 Apr 2024</td>

                                <td>List Price</td>

                                <td>2</td>

                                <td class="fw-semibold">
                                    ₹2,15,000
                                </td>

                                <td>
                                    <span class="status-badge created">
                                        Created
                                    </span>
                                </td>

                            </tr>

                            <tr>

                                <td>
                                    <a href="#"
                                        class="order-link">

                                        ORD-2024-1828

                                    </a>
                                </td>

                                <td>07 Apr 2024</td>

                                <td>List Price</td>

                                <td>6</td>

                                <td class="fw-semibold">
                                    ₹3,42,000
                                </td>

                                <td>
                                    <span class="status-badge dispatched">
                                        Dispatched
                                    </span>
                                </td>

                            </tr>

                            <tr>

                                <td>
                                    <a href="#"
                                        class="order-link">

                                        ORD-2024-1820

                                    </a>
                                </td>

                                <td>05 Apr 2024</td>

                                <td>List Price</td>

                                <td>7</td>

                                <td class="fw-semibold">
                                    ₹4,10,000
                                </td>

                                <td>
                                    <span class="status-badge pending">
                                        Pending
                                    </span>
                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>
    </div>
</body>

</html>
<style>
    /* PAGE */

    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 22px;
    }

    .page-subtitle {
        font-size: 15px;
        color: #64748b;
        font-weight: 500;
    }

    /* BUTTON */

    .new-order-btn {
        height: 40px;
        padding: 0 18px;
        border: none;
        border-radius: 10px;
        background: #1565d8;
        color: white;
        font-size: 14px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* CARD */

    .booking-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        overflow: hidden;
    }

    /* HEADER */

    .booking-header {
        padding: 22px 24px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .booking-title {
        font-size: 16px;
        font-weight: 600;
        color: #0f172a;
    }

    .booking-actions {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* SEARCH */

    .search-box {
        width: 200px;
        height: 40px;
        border: 1px solid #dbe2ea;
        border-radius: 10px;
        display: flex;
        align-items: center;
        padding: 0 14px;
        gap: 10px;
    }

    .search-box i {
        color: #64748b;
        font-size: 14px;
    }

    .search-box input {
        border: none;
        outline: none;
        width: 100%;
        font-size: 14px;
    }

    /* SELECT */

    .filter-select {
        height: 40px;
        min-width: 145px;
        border: 1px solid #dbe2ea;
        border-radius: 10px;
        padding: 0 14px;
        background: white;
        font-size: 14px;
        color: #334155;
    }

    .active-filter {
        border: 2px solid #2563eb;
    }

    /* TABLE */

    .booking-table {
        width: 100%;
        border-collapse: collapse;
    }

    .booking-table thead th {
        font-size: 13px;
        font-weight: 500;
        color: #64748b;
        padding: 14px 24px;
        border-bottom: 1px solid #e2e8f0;
        text-align: left;
    }

    .booking-table tbody td {
        padding: 18px 24px;
        font-size: 14px;
        color: #0f172a;
        border-bottom: 1px solid #e2e8f0;
    }

    .booking-table tbody tr:last-child td {
        border-bottom: none;
    }

    /* LINK */

    .order-link {
        color: #0f62fe;
        text-decoration: none;
        font-weight: 600;
    }

    /* BADGE */

    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .created {
        background: #dbeafe;
        color: #2563eb;
    }

    .dispatched {
        background: #dcfce7;
        color: #16a34a;
    }

    .pending {
        background: #fef3c7;
        color: #d97706;
    }

    /* MOBILE */

    @media(max-width:992px) {

        .booking-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }

        .booking-actions {
            width: 100%;
            flex-wrap: wrap;
        }

    }

    @media(max-width:768px) {

        .page-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 14px;
        }

        .new-order-btn {
            width: 100%;
            justify-content: center;
        }

        .search-box {
            width: 100%;
        }

        .filter-select {
            flex: 1;
        }

        .booking-table {
            min-width: 800px;
        }

    }

    /* GRID */

    .complaint-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
    }

    /* FULL WIDTH */

    .full-width {
        grid-column: 1 / -1;
    }

    /* LABEL */

    .form-group label {
        font-size: 14px;
        font-weight: 600;
        color: #0f172a;
        margin-bottom: 10px;
        display: block;
    }

    /* REQUIRED */

    .required {
        color: #ef4444;
    }

    /* INPUT */

    .custom-input {
        width: 100%;
        height: 42px;
        border: 1px solid #dbe2ea;
        border-radius: 10px;
        padding: 0 14px;
        font-size: 14px;
        color: #0f172a;
        outline: none;
        background: #fff;
    }

    .custom-input:focus {
        border-color: #1565d8;
    }

    /* TEXTAREA */

    .custom-textarea {
        width: 100%;
        min-height: 140px;
        border: 1px solid #dbe2ea;
        border-radius: 10px;
        padding: 14px;
        font-size: 14px;
        color: #0f172a;
        outline: none;
        resize: vertical;
        background: #fff;
    }

    .custom-textarea:focus {
        border-color: #1565d8;
    }

    /* BUTTON WRAPPER */

    .complaint-btn-wrapper {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 18px;
    }

    /* CANCEL */

    .cancel-btn {
        height: 40px;
        padding: 0 20px;
        border: 1px solid #dbe2ea;
        background: #fff;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        color: #0f172a;
    }

    /* SUBMIT */

    .submit-btn {
        height: 40px;
        padding: 0 20px;
        border: none;
        background: #1565d8;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        color: #fff;
    }

    /* MOBILE */

    @media(max-width:768px) {

        .complaint-grid {
            grid-template-columns: 1fr;
        }

        .complaint-btn-wrapper {
            flex-direction: column;
        }

        .cancel-btn,
        .submit-btn {
            width: 100%;
        }

    }
</style>
</body>

</html>

<script>
    // OPEN FORM

    const openOrderForm = document.getElementById('openOrderForm');

    const closeOrderForm = document.getElementById('closeOrderForm');

    const orderFormCard = document.getElementById('orderFormCard');

    // OPEN

    openOrderForm.addEventListener('click', function() {

        orderFormCard.classList.add('show');

        openOrderForm.style.display = 'none';

        closeOrderForm.classList.add('show');

    });

    // CLOSE

    closeOrderForm.addEventListener('click', function() {

        orderFormCard.classList.remove('show');

        closeOrderForm.classList.remove('show');

        openOrderForm.style.display = 'flex';

    });
</script>