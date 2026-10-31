# Value markers

A sentence that shows a value, such as a user's name or a count, is one phrase however many values it is shown with. Rendered HTML alone can't say which part was a variable, so whatever prints the value marks it:

```html
<p>Hello <!--ls:name-->Ana<!--/ls-->, welcome back</p>
<!-- where comments can't be written: -->
<p>Hello <span data-ls-param="name">Ana</span>, welcome back</p>
```

`translatePage()` and `translateContentBlock()` read either form as the phrase `Hello {name}, welcome back` with `name` set to `Ana`. Every visitor's render registers that one phrase, a translation places the value where its `{name}` is, and the output keeps the marker around the value. A plural or select in the translation chooses on the value itself.

This is how a template integration keeps rule 5 of [invariants.md](./invariants.md) without the template writing `{name}` itself. A framework binding marks what its templates print. In hand-written PHP templates, writing `{name}` in the markup and passing params is simpler.

| Markup | Registers |
|---|---|
| `<p>Hello <!--ls:name-->Ana<!--/ls--></p>` | The phrase `Hello {name}` |
| `<p>Hi <b><!--ls:name-->Ana<!--/ls--></b>, you have <!--ls:count-->3<!--/ls--> messages</p>` | One block: `Hi` · `{name}` · `, you have {count} messages` |
| `<td><!--ls:total-->89.00<!--/ls--> <!--ls:currency-->EUR<!--/ls--></td>` | Nothing: it has no text of its own |
| `<p>Hello <!--ls:Name-->Ana<!--/ls--></p>` | Nothing: a name must match `[a-z][a-z0-9_]*` |

## Naming

A name is lowercase, starts with a letter, and holds only letters, digits and `_`. A value that can't be named is never registered, with one debug notice, because registering it with its value written in would register one phrase per value.

## What is not a marker

- A param you pass yourself under the same name wins over the marked value.
- A pair with an element inside it, a pair left open, and any other comment are ordinary markup.
- Text with no markers registers what it renders, values included. That is rule 1 of [invariants.md](./invariants.md) again.

A line `translateRich()` renders from your language files gives a marked value back as its marker, so the page around it can still be read ([migration.md](./migration.md)).
