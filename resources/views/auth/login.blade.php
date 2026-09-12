<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesion | Cauchos Alfa</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @if (config('recaptchav3.sitekey'))
        {!! RecaptchaV3::initJs() !!}
    @endif
    <style>
        :root {
            --red: #d81e05;
            --red-dark: #b31805;
            --void: #0a0a0b;
            --charcoal: #141416;
        }

        body {
            background: radial-gradient(ellipse at top, rgba(216, 30, 5, .15), transparent 60%), var(--void);
            color: #f2f2f2;
            font-family: 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .login-card {
            background: var(--charcoal);
            border-radius: 28px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            width: 100%;
            max-width: 420px;
        }

        .login-icon {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: rgba(216, 30, 5, 0.1);
            color: var(--red);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .text-muted-custom {
            color: rgba(255, 255, 255, 0.5) !important;
        }

        .login-card .form-control {
            background: var(--void);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            border-radius: 12px;
        }

        .login-card .form-control:focus {
            background: var(--void);
            color: white;
            border-color: var(--red);
            box-shadow: 0 0 0 .2rem rgba(216, 30, 5, .25);
        }

        .btn-red {
            background: var(--red);
            border-color: var(--red);
            color: #fff;
            font-weight: 600;
        }

        .btn-red:hover,
        .btn-red:focus {
            background: var(--red-dark);
            border-color: var(--red-dark);
            color: #fff;
        }

        .custom-control-input:checked~.custom-control-label::before {
            background-color: var(--red);
            border-color: var(--red);
        }

        .back-link {
            color: rgba(255, 255, 255, .5);
            text-decoration: none;
        }

        .back-link:hover {
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="login-card shadow-lg">
        <div class="card-body p-5 text-center">
            <div class="mb-4">
                <div class="login-icon">
                    <i class="bi bi-shield-lock-fill" style="font-size: 2.5rem;"></i>
                </div>
            </div>

            <h2 class="font-weight-bold mb-2">Panel administrativo</h2>
            <p class="text-muted-custom mb-4">Ingresa tus credenciales para continuar.</p>

            @if (session('error'))
                <div class="alert alert-danger border-0 text-white" style="background: rgba(220, 53, 69, 0.2);">
                    {{ session('error') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger border-0 text-white" style="background: rgba(220, 53, 69, 0.2);">
                    <ul class="mb-0 small">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form id="admin-login-form" action="{{ route('login.store') }}" method="POST">
                @csrf
                @if (config('recaptchav3.sitekey'))
                    {!! RecaptchaV3::field('login') !!}
                @endif
                <div class="form-group text-left">
                    <label for="email" class="small font-weight-bold text-white-50">CORREO ELECTRÓNICO</label>
                    <input id="email" name="email" type="email"
                        class="form-control form-control-lg @error('email') is-invalid @enderror"
                        value="{{ old('email') }}" required autofocus>
                    @error('email') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>
                <div class="form-group text-left">
                    <label for="password" class="small font-weight-bold text-white-50">CONTRASEÑA</label>
                    <input id="password" name="password" type="password"
                        class="form-control form-control-lg @error('password') is-invalid @enderror" required>
                    @error('password') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>
                <div class="form-group form-action-d-flex mb-3 d-flex align-items-center">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="remember" name="remember" value="1">
                        <label class="custom-control-label text-muted-custom" for="remember">Recordarme</label>
                    </div>
                    <button class="btn btn-red btn-lg ml-auto px-4 shadow">INGRESAR</button>
                </div>
            </form>

            <div class="mt-4">
                <a href="{{ route('site.home') }}" class="back-link small">
                    <i class="bi bi-arrow-left mr-1"></i> Volver al inicio
                </a>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    @if (config('recaptchav3.sitekey'))
        <script>
            document.getElementById('admin-login-form')?.addEventListener('submit', function (e) {
                var form = this;
                var input = form.querySelector('input[name="g-recaptcha-response"]');
                if (typeof grecaptcha !== 'undefined' && input && !form.dataset.recaptchaRefreshed) {
                    e.preventDefault();
                    grecaptcha.ready(function () {
                        grecaptcha.execute('{{ config('recaptchav3.sitekey') }}', { action: 'login' }).then(function (token) {
                            input.value = token;
                            form.dataset.recaptchaRefreshed = 'true';
                            form.submit();
                        }).catch(function () {
                            form.dataset.recaptchaRefreshed = 'true';
                            form.submit();
                        });
                    });
                }
            });
        </script>
    @endif
</body>
</html>