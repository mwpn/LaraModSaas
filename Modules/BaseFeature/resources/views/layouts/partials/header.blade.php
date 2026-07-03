<header>
    <div>
        <strong>{{ tenant('name') ?? tenant('id') }}</strong>
    </div>
    <div>
        <form method="POST" action="{{ url('/logout') }}">
            @csrf
            <button class="tenant-btn" type="submit">Logout</button>
        </form>
    </div>
</header>
