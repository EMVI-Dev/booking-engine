<?php

use App\Services\CsvExportService;

test('formula-like cells are neutralised', function (string $input, string $expected) {
    expect(CsvExportService::safeCell($input))->toBe($expected);
})->with([
    ['=HYPERLINK("http://evil","x")', '\'=HYPERLINK("http://evil","x")'],
    ['@SUM(A1)', "'@SUM(A1)"],
    ['+cmd|calc', "'+cmd|calc"],
    ["\t=1", "'\t=1"],
    ['+62 812-3456-789', '+62 812-3456-789'],
    ['-150000', '-150000'],
    ['Budi', 'Budi'],
]);

test('rows are written with neutralised cells', function () {
    $handle = fopen('php://memory', 'w+');
    CsvExportService::writeRow($handle, ['=1+1', 5, null]);
    rewind($handle);

    expect(stream_get_contents($handle))->toBe("'=1+1,5,\n");
});
