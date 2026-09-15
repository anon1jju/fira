<?php
$currentPage = basename($_SERVER['PHP_SELF']);

// Kumpulan menu agar kode HTML lebih rapi dan mudah di-maintenance
$menus = [
    [
        'url' => 'dashboard.php',
        'label' => 'Dashboard',
        'icon' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>'
    ],
    [
        'url' => 'produk.php',
        'label' => 'Produk',
        'icon' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>'
    ],
    [
        'url' => 'agen.php',
        'label' => 'Transaksi',
        'icon' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>'
    ],
    [
        'url' => 'setoran.php',
        'label' => 'Setoran',
        'icon' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>'
    ],
    [
        'url' => '#',
        'label' => 'Pengaturan',
        'icon' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>'
    ]
];
?>

<style>
    /* Menyembunyikan scrollbar pada perangkat mobile agar lebih rapi */
    .hide-scrollbar::-webkit-scrollbar { display: none; }
    .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<aside class="w-full lg:w-64 bg-slate-900 border-r border-slate-800 text-slate-300 flex-shrink-0 flex flex-col lg:min-h-screen relative z-20">
    
    <!-- Bagian Logo (Sticky di atas saat mobile) -->
    <div class="px-5 py-5 lg:py-8 flex items-center gap-4 bg-slate-900 border-b lg:border-none border-slate-800 lg:mb-2">
        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center font-bold text-white text-xl shadow-lg shadow-blue-900/30">
            F
        </div>
        <div>
            <h2 class="text-xl font-bold leading-tight text-white tracking-wide">Fira</h2>
            <span class="text-xs font-medium text-blue-400 uppercase tracking-wider">Admin Panel</span>
        </div>
    </div>

    <!-- Navigasi -->
    <nav class="flex lg:flex-col gap-1.5 lg:gap-2 px-4 py-3 lg:py-0 overflow-x-auto lg:overflow-visible hide-scrollbar lg:flex-1">
        
        <?php foreach ($menus as $menu): ?>
            <?php 
                $isActive = $currentPage === $menu['url']; 
                // Jika aktif, tampilkan warna biru solid. Jika tidak, transparan dengan efek hover.
                $activeClass = $isActive 
                    ? 'bg-blue-600 text-white shadow-md shadow-blue-900/20 font-semibold' 
                    : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800 font-medium';
            ?>
            <a href="<?= htmlspecialchars($menu['url']) ?>" 
               class="flex items-center gap-3 whitespace-nowrap px-4 py-3 rounded-xl transition-all duration-200 <?= $activeClass ?>">
                
                <?= $menu['icon'] ?>
                <span><?= htmlspecialchars($menu['label']) ?></span>
                
                <?php if ($isActive): ?>
                    <!-- Indikator dot kecil di sebelah kanan menu aktif (hanya di Desktop) -->
                    <span class="hidden lg:block w-1.5 h-1.5 rounded-full bg-white ml-auto"></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>

    </nav>

    <!-- Footer Sidebar (Hanya tampil di Desktop) -->
    <div class="hidden lg:block p-6 mt-auto">
        <div class="rounded-xl bg-slate-800/50 p-4 border border-slate-800">
            <div class="text-xs text-slate-400 text-center">
                &copy; <?= date('Y') ?> Fira App<br>v1.0.0
            </div>
        </div>
    </div>
</aside>