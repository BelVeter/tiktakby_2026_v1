@extends('layouts.app')

@section('page-title', 'Избранное — TikTak')
@section('meta-description', 'Ваши избранные товары на tiktak.by')
@section('style')
    <link rel="stylesheet" href="/public/css/pages/l2.css?v=3">
@endsection

@section('content')
    <div class="container-app" style="padding: 30px 20px; min-height: 60vh;">
        <h1 style="font-family: 'Nunito', sans-serif; font-size: 28px; font-weight: 700; color: #333; margin-bottom: 24px;">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="#E53935" stroke="#E53935" stroke-width="2"
                style="vertical-align: middle; margin-right: 8px;">
                <path
                    d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z">
                </path>
            </svg>
            Избранное
        </h1>

        {{-- Loading state --}}
        <div id="favorites-loading" style="text-align: center; padding: 60px 20px;">
            <div
                style="display: inline-block; width: 40px; height: 40px; border: 4px solid #e0e0e0; border-top-color: #3180D1; border-radius: 50%; animation: fav-spin 0.8s linear infinite;">
            </div>
            <p style="font-family: 'Nunito', sans-serif; font-size: 16px; color: #999; margin-top: 16px;">Загрузка избранных
                товаров...</p>
        </div>
        <style>
            @keyframes fav-spin {
                to {
                    transform: rotate(360deg);
                }
            }
        </style>

        {{-- Empty state (shown when no favorites) --}}
        <div id="favorites-empty" style="display: none; text-align: center; padding: 60px 20px;">
            <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#C1D9F3" stroke-width="1.5"
                style="margin-bottom: 20px;">
                <path
                    d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z">
                </path>
            </svg>
            <p style="font-family: 'Nunito', sans-serif; font-size: 20px; color: #999; margin-bottom: 12px;">В избранном
                пока пусто</p>
            <p style="font-family: 'Nunito', sans-serif; font-size: 16px; color: #bbb; margin-bottom: 24px;">Нажмите на
                сердечко на карточке товара, чтобы добавить его в избранное</p>
            <a href="/ru/"
                style="display: inline-block; padding: 12px 32px; background: #3180D1; color: #fff; border-radius: 8px; font-family: 'Nunito', sans-serif; font-weight: 600; font-size: 16px; text-decoration: none; transition: background 0.2s;">
                Перейти в каталог
            </a>
        </div>

        {{-- Favorites grid (populated via AJAX with full server-rendered cards) --}}
        <div id="favorites-grid" class="l2-cards-container" style="display: none;"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var favorites = JSON.parse(localStorage.getItem('tiktak_favorites') || '{}');
            var ids = Object.keys(favorites);
            var loadingEl = document.getElementById('favorites-loading');
            var emptyEl = document.getElementById('favorites-empty');
            var gridEl = document.getElementById('favorites-grid');

            if (ids.length === 0) {
                loadingEl.style.display = 'none';
                emptyEl.style.display = 'block';
                return;
            }

            // Load full product cards from server via AJAX
            var csrfToken = document.querySelector('meta[name="csrf-token"]');
            var token = csrfToken ? csrfToken.getAttribute('content') : '';

            var xhr = new XMLHttpRequest();
            xhr.open('POST', '/favorites/cards', true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.setRequestHeader('X-CSRF-TOKEN', token);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.onload = function () {
                loadingEl.style.display = 'none';

                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data.count > 0 && data.html) {
                            gridEl.innerHTML = data.html;
                            gridEl.style.display = '';

                            // Re-initialize heart icons for the loaded cards
                            if (typeof window.TiktakFavorites !== 'undefined') {
                                window.TiktakFavorites.initHearts();
                            }
                        } else {
                            emptyEl.style.display = 'block';
                        }
                    } catch (e) {
                        emptyEl.style.display = 'block';
                    }
                } else {
                    emptyEl.style.display = 'block';
                }
            };

            xhr.onerror = function () {
                loadingEl.style.display = 'none';
                emptyEl.style.display = 'block';
            };

            xhr.send(JSON.stringify({ ids: ids.map(Number) }));
        });
    </script>
@endsection