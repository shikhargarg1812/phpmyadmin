var Sql = require('../../js/src/sql');

test('test1', () => {
    var testPerson = Sql.urlDecode("phpmyadmin");
    expect(testPerson).toEqual("phpmyadmin");
});
