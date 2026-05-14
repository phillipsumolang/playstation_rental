<?php $__env->startSection('title', 'Verifikasi Email'); ?>

<?php $__env->startSection('page-style'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/css/pages/page-auth.css')); ?>">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
    <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner py-4">
            <div class="card px-sm-4 px-2">
                <div class="card-body">
                    <div class="app-brand justify-content-center mb-3">
                        <img src="<?php echo e(asset('/assets/img/Logo-Warnet.jpg')); ?>" alt="Logo" style="width: 220px; height: 88px;"
                            class="rounded-pill">
                    </div>

                    <h4 class="mb-2 text-center">Verifikasi Email Anda</h4>
                    <p class="mb-4 text-center">
                        Kami sudah mengirim link verifikasi ke email Anda. Silakan buka email tersebut lalu klik link verifikasi
                        sebelum mulai booking.
                    </p>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('status') === 'verification-link-sent'): ?>
                        <div class="alert alert-success" role="alert">
                            Link verifikasi baru berhasil dikirim.
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <div class="alert alert-warning" role="alert">
                        Setelah email diverifikasi, Anda bisa langsung lanjut booking PlayStation.
                    </div>

                    <form method="POST" action="<?php echo e(route('verification.send')); ?>" class="mb-3">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="btn btn-primary d-grid w-100">
                            Kirim Ulang Email Verifikasi
                        </button>
                    </form>

                    <form method="POST" action="<?php echo e(route('logout')); ?>">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="btn btn-outline-secondary d-grid w-100">
                            Keluar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.blankLayout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/philipsumolang/Herd/playstation_rental/resources/views/auth/verify-email.blade.php ENDPATH**/ ?>