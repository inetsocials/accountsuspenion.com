<?php
declare(strict_types=1);

namespace DR\Http;

use DR\Core\App;
use DR\Core\Audit;
use DR\Core\Db;
use DR\Core\Request;

final class CatalogController extends Controller
{
    public function index(array $p, ?array $u): void
    {
        $this->view('admin/catalog', ['title' => 'Service catalog', 'rows' => Db::all('SELECT * FROM catalog ORDER BY active DESC, name')], 'app');
    }

    public function save(array $p, ?array $u): void
    {
        $name = Request::str('name', 160);
        if ($name === '') {
            $this->fail('Enter a service name.', '/admin/catalog');
        }
        $data = [
            'name' => $name, 'description' => Request::text('description', 2000) ?: null, 'unit_price' => to_minor(Request::str('unit_price', 20)),
            'vat_bp' => max(0, min(10000, (int) round((float) Request::str('vat', 6) * 100))), 'active' => !empty($_POST['active']) ? 1 : 0,
        ];
        $id = Request::int('id');
        if ($id && Db::value('SELECT id FROM catalog WHERE id = ?', [$id])) {
            Db::update('catalog', $data, 'id = :id', ['id' => $id]);
        } else {
            $id = Db::insert('catalog', $data + ['created_at' => now()]);
        }
        Audit::log('catalog_saved', 'catalog', $id, [], $u);
        $this->flash('ok', 'Service saved.');
        App::redirect('/admin/catalog');
    }
}
