<x-field :label="__('books.labels.title')" name="title" required>
    <x-input name="title" :value="old('title', $title->title)" required />
</x-field>

<x-field :label="__('books.labels.subject')" name="subject">
    <x-input name="subject" :value="old('subject', $title->subject)" />
</x-field>

<x-field :label="__('books.labels.grade')" name="grade" required>
    <x-input name="grade" type="number" min="1" max="13" :value="old('grade', $title->grade)" required />
</x-field>

<x-field :label="__('books.labels.isbn')" name="isbn">
    <x-input name="isbn" :value="old('isbn', $title->isbn)" />
</x-field>

<x-field :label="__('books.labels.total_copies')" name="total_copies" required>
    <x-input name="total_copies" type="number" min="0" max="5000" :value="old('total_copies', $title->total_copies ?? 0)" required />
</x-field>
