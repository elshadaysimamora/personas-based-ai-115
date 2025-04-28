<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Auth;


class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Jika request bukan ke area admin, lewati middleware ini
        if (!$request->is('admin*')) {
            return $next($request);
        }

        // Izinkan akses ke halaman login admin
        if ($request->is('admin/login')) {
            return $next($request);
        }

        // Cek autentikasi
        if (!Auth::check()) {
            return redirect()->to('/admin/login');
        }

        // Cek status admin
        if (!Auth::user()->is_admin) {
            return redirect()->route('chat')->with('error', 'Only administrators can access this area.');
        }

        return $next($request);
    }}
