import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const scenarios = [
  {
    name: 'Wasmer external script',
    src: 'https://detailing-devils-web.wasmer.app/assets/js/modules/PAGE.js',
    base: 'https://detailing-devils-web.wasmer.app/studio-tour',
    expected: 'https://detailing-devils-web.wasmer.app/assets/js/modules/frame-loader.js',
  },
  {
    name: 'PageSpeed external script',
    src: 'https://www.staging.detailingdevils.com/assets/js/modules/PAGE.js.pagespeed.ce.example.js',
    base: 'https://www.staging.detailingdevils.com/studio-tour',
    expected: 'https://www.staging.detailingdevils.com/assets/js/modules/frame-loader.js',
  },
  {
    name: 'external asset host with a query string',
    src: 'https://assets.example.test/custom/modules/PAGE.js?v=21',
    base: 'https://www.staging.detailingdevils.com/',
    expected: 'https://assets.example.test/custom/modules/frame-loader.js',
  },
  ...['https://detailing-devils-web.wasmer.app', 'https://www.staging.detailingdevils.com'].flatMap((origin) =>
    ['/', '/studio-tour'].map((path) => ({
      name: `inline script on ${origin}${path}`,
      src: '',
      base: `${origin}${path}`,
      expected: `${origin}/assets/js/modules/frame-loader.js`,
    }))),
  {
    name: 'no current script element',
    src: null,
    base: 'https://www.staging.detailingdevils.com/studio-tour',
    expected: 'https://www.staging.detailingdevils.com/assets/js/modules/frame-loader.js',
  },
];

for (const page of ['home', 'studio-tour']) {
  const source = fs.readFileSync(new URL(`../../public/assets/js/modules/${page}.js`, import.meta.url), 'utf8');
  const declaration = source.match(/const loaderModule =[\s\S]*?;/)?.[0];
  assert.ok(declaration, `${page} must resolve its loader before awaiting the import`);

  for (const scenario of scenarios) {
    test(`${page}: resolves the loader for ${scenario.name}`, () => {
      const document = {
        currentScript: scenario.src === null ? null : { src: scenario.src.replace('PAGE', page) },
        baseURI: scenario.base,
      };
      const actual = vm.runInNewContext(`${declaration}\nloaderModule.href;`, { URL, document });
      assert.equal(actual, scenario.expected);
    });
  }
}
