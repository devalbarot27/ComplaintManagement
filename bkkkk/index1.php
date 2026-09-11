<!doctype html>
<html lang="en">

<head>

	<meta charset="UTF-8">

	<meta name="viewport"
		content="width=device-width, initial-scale=1.0">

	<title>Dealer Portal Login</title>

	<!-- GOOGLE FONT -->

	<link rel="preconnect"
		href="https://fonts.googleapis.com">

	<link rel="preconnect"
		href="https://fonts.gstatic.com"
		crossorigin>

	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
		rel="stylesheet">

	<!-- BOOTSTRAP -->

	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
		rel="stylesheet">

	<!-- ICONS -->

	<link rel="stylesheet"
		href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

	<style>
		* {
			margin: 0;
			padding: 0;
			box-sizing: border-box;
		}

		body {
			font-family: 'Inter', sans-serif;
			background: #f1f5f9;
			height: 100vh;
			overflow: hidden;
		}

		.login-page {
			width: 100%;
			height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 20px;
		}

		/* CARD */

		.login-card {
			width: 100%;
			max-width: 980px;
			min-height: 620px;
			background: #fff;
			border-radius: 22px;
			overflow: hidden;
			display: flex;
			box-shadow: 0 10px 35px rgba(15, 23, 42, 0.08);
		}

		/* LEFT */

		.left-panel {
			width: 45%;
			background: linear-gradient(135deg, #1565d8, #0f4db3);
			padding: 50px;
			color: #fff;
			display: flex;
			flex-direction: column;
			justify-content: center;
			position: relative;
		}

		.brand-logo {
			width: 70px;
			height: 70px;
			border-radius: 18px;
			background: rgba(255, 255, 255, 0.15);
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 28px;
			margin-bottom: 24px;
		}

		.left-panel h2 {
			font-size: 34px;
			font-weight: 700;
			margin-bottom: 14px;
			line-height: 1.3;
		}

		.left-panel p {
			font-size: 15px;
			line-height: 1.8;
			opacity: .9;
			margin-bottom: 30px;
		}

		.signup-btn {
			width: max-content;
			height: 46px;
			padding: 0 26px;
			border: 1px solid rgba(255, 255, 255, 0.35);
			border-radius: 12px;
			background: rgba(255, 255, 255, 0.12);
			color: #fff;
			font-size: 14px;
			font-weight: 600;
			display: flex;
			align-items: center;
			justify-content: center;
			text-decoration: none;
			backdrop-filter: blur(8px);
		}

		.signup-btn:hover {
			background: #fff;
			color: #1565d8;
		}

		/* RIGHT */

		.right-panel {
			width: 55%;
			padding: 50px;
			display: flex;
			flex-direction: column;
			justify-content: center;
		}

		.login-top {
			display: flex;
			align-items: center;
			justify-content: space-between;
			margin-bottom: 30px;
		}

		.login-title {
			font-size: 28px;
			font-weight: 700;
			color: #0f172a;
			margin-bottom: 6px;
		}

		.login-subtitle {
			font-size: 14px;
			color: #64748b;
		}

		/* SOCIAL */

		.social-icons {
			display: flex;
			gap: 10px;
		}

		.social-btn {
			width: 42px;
			height: 42px;
			border-radius: 12px;
			border: 1px solid #dbe2ea;
			display: flex;
			align-items: center;
			justify-content: center;
			text-decoration: none;
			color: #334155;
			font-size: 16px;
			transition: .2s;
		}

		.social-btn:hover {
			background: #1565d8;
			color: #fff;
			border-color: #1565d8;
		}

		/* FORM */

		.form-group {
			margin-bottom: 20px;
		}

		.form-label {
			font-size: 14px;
			font-weight: 600;
			color: #0f172a;
			margin-bottom: 10px;
		}

		.custom-input {
			width: 100%;
			height: 50px;
			border: 1px solid #dbe2ea;
			border-radius: 14px;
			padding: 0 16px;
			font-size: 14px;
			outline: none;
			transition: .2s;
		}

		.custom-input:focus {
			border-color: #1565d8;
			box-shadow: 0 0 0 4px rgba(21, 101, 216, 0.08);
		}

		/* OPTIONS */

		.form-options {
			display: flex;
			align-items: center;
			justify-content: space-between;
			margin-bottom: 24px;
		}

		.remember {
			display: flex;
			align-items: center;
			gap: 8px;
			font-size: 14px;
			color: #475569;
		}

		.forgot-link {
			font-size: 14px;
			color: #1565d8;
			text-decoration: none;
			font-weight: 500;
		}

		/* BUTTON */

		.login-btn {
			width: 100%;
			height: 52px;
			border: none;
			border-radius: 14px;
			background: #1565d8;
			color: #fff;
			font-size: 15px;
			font-weight: 600;
			transition: .2s;
		}

		.login-btn:hover {
			background: #0f4db3;
		}

		/* MOBILE */

		@media(max-width:992px) {

			body {
				overflow: auto;
			}

			.login-page {
				height: auto;
				min-height: 100vh;
				padding: 20px 14px;
			}

			.login-card {
				flex-direction: column;
				min-height: auto;
			}

			.left-panel {
				width: 100%;
				padding: 40px 30px;
				text-align: center;
				align-items: center;
			}

			.right-panel {
				width: 100%;
				padding: 40px 24px;
			}

			.login-top {
				flex-direction: column;
				align-items: flex-start;
				gap: 18px;
			}

		}

		@media(max-width:576px) {

			.left-panel h2 {
				font-size: 28px;
			}

			.login-title {
				font-size: 24px;
			}

			.form-options {
				flex-direction: column;
				align-items: flex-start;
				gap: 12px;
			}

		}
	</style>

</head>

<body>

	<div class="login-page">

		<div class="login-card">

			<!-- LEFT -->

			<div class="left-panel">

				<div class="brand-logo">

					<i class="bi bi-grid"></i>

				</div>

				<h2>
					Welcome to Dealer Portal
				</h2>

				<p>
					Manage orders, dispatch and dealer operations
					with a modern responsive dashboard.
				</p>

				<a href="#"
					class="signup-btn">

					Create Account

				</a>

			</div>

			<!-- RIGHT -->

			<div class="right-panel">

				<!-- TOP -->

				<div class="login-top">

					<div>

						<div class="login-title">
							Sign In
						</div>

						<div class="login-subtitle">
							Login to continue to your dashboard
						</div>

					</div>

					<!-- SOCIAL -->

					<div class="social-icons">

						<a href="#"
							class="social-btn">

							<i class="bi bi-facebook"></i>

						</a>

						<a href="#"
							class="social-btn">

							<i class="bi bi-twitter-x"></i>

						</a>

					</div>

				</div>

				<!-- FORM -->

				<form>

					<!-- USERNAME -->

					<div class="form-group">

						<label class="form-label">
							Username
						</label>

						<input type="text"
							class="custom-input"
							placeholder="Enter username">

					</div>

					<!-- PASSWORD -->

					<div class="form-group">

						<label class="form-label">
							Password
						</label>

						<input type="password"
							class="custom-input"
							placeholder="Enter password">

					</div>

					<!-- OPTIONS -->

					<div class="form-options">

						<label class="remember">

							<input type="checkbox">

							Remember me

						</label>

						<a href="#"
							class="forgot-link">

							Forgot Password?

						</a>

					</div>

					<!-- BUTTON -->

					<button type="submit"
						class="login-btn">

						Sign In

					</button>

				</form>

			</div>

		</div>

	</div>

</body>

</html>