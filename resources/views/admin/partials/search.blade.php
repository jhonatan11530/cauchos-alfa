<div class="page-inner mt--5 mb-0 pt-3 pb-0"><div class="card"><div class="card-body py-3">
    <form method="GET" action="{{ url()->current() }}" class="form-inline">
        <div class="row g-2 align-items-center w-100">
            <div class="col-md-8"><input type="text" name="q" value="{{ request('q') }}" class="form-control w-100" placeholder="Buscar..."></div>
            <div class="col-md-4">
                <button class="btn btn-primary"><i class="fas fa-search"></i> Buscar</button>
                <a href="{{ url()->current() }}" class="btn btn-secondary">Limpiar</a>
            </div>
        </div>
    </form>
</div></div></div>