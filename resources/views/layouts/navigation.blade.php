<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container">
        <a class="navbar-brand" href="{{ route('dashboard') }}">
            {{ config('app.name', 'Laravel') }}
        </a>

        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
             <ul class="navbar-nav mr-auto">
                 <li class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                     <a class="nav-link" href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
                 </li>
                 @if(Auth::user()->facebookAccount)
                 <li class="nav-item {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                     <a class="nav-link" href="{{ route('settings.index') }}">
                         <i class="fas fa-cog"></i> Settings
                     </a>
                 </li>
                 <li class="nav-item {{ request()->routeIs('messenger.*') ? 'active' : '' }}">
                     <a class="nav-link" href="{{ route('messenger.index') }}">
                         <i class="fas fa-comments"></i> Messenger
                     </a>
                 </li>
                 @endif
                 <li class="nav-item {{ request()->routeIs('messenger.whatsapp.*') ? 'active' : '' }}">
                     <a class="nav-link" href="{{ route('messenger.whatsapp.index') }}">
                         <i class="fas fa-comments"></i> Whatsapp
                     </a>
                 </li>
             </ul>

            @auth
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            {{ Auth::user()->name }}
                        </a>
                        <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <a class="dropdown-item" href="{{ route('profile.edit') }}">{{ __('Profile') }}</a>
                            <div class="dropdown-divider"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item">{{ __('Log Out') }}</button>
                            </form>
                        </div>
                    </li>
                </ul>
            @endauth
        </div>
    </div>
</nav>
