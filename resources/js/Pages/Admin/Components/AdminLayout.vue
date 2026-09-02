<template>
    <div class="antialiased bg-gray-50 dark:bg-gray-900">
        <!-- navbar start -->
        <Navbar />
        <!-- navbar end -->
        <!-- Sidebar -->
        <Sidebar />

        <!-- end -->
        <div class="page-enter-anim">
            <slot />
        </div>
    </div>
</template>

<script setup>


import Navbar from './Navbar.vue';
import Sidebar from './Sidebar.vue';




</script>

<style></style>