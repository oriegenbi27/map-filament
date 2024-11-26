<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SportsFacilityResource\Pages;
use App\Filament\Resources\SportsFacilityResource\RelationManagers;
use App\Models\District;
use App\Models\FacilityType;
use App\Models\Kelurahan;
use App\Models\SportsFacility;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\ViewField;
use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\View;
use Filament\Forms\Set;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Livewire\Livewire;

class SportsFacilityResource extends Resource
{
    protected static ?string $model = SportsFacility::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationGroup = 'Sports Facilities';
    protected static ?string $navigationLabel = 'Data Sarana';
    protected string $view = 'components.images-column';
    protected $listeners = ['updateMap'];


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nama Tempat')
                    ->required(),
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->unique(FacilityType::class, 'slug')
                    ->disabled(),
                Toggle::make('is_government_owned')
                    ->label('Milik Pemerintah'),
                Toggle::make('is_private_owned')
                    ->label('Milik Swasta'),
                TextInput::make('ijin_pbg')
                    ->label('Ijin PBG')
                    ->nullable(),
                Select::make('facility_type_id')
                    ->label('Jenis Tempat Olahraga')
                    ->options(FacilityType::all()->pluck('name', 'id')) // Ambil daftar jenis tempat olahraga
                    ->searchable()
                    ->required(),
                TextInput::make('capacity')
                    ->label('Kapasitas')
                    ->numeric(),
                TextInput::make('employees_count')
                    ->label('Jumlah Karyawan')
                    ->numeric(),
                TextInput::make('land_area')
                    ->label('Luas Lahan')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->placeholder('Contoh: 120.50')
                    ->required(),

                Select::make('unit')
                    ->label('Satuan Luas')
                    ->options([
                        'm²' => 'Meter Persegi (m²)',
                        'ha' => 'Hektar (ha)',
                        'ft²' => 'Kaki Persegi (ft²)',
                    ])
                    ->default('m²')
                    ->required(),

                TextInput::make('luas_bangunan')
                    ->label('Luas Bangunan')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->placeholder('Contoh: 120.50')
                    ->required(),

                Select::make('unit_luas_bangunan')
                    ->label('Satuan Luas Bangunan')
                    ->options([
                        'm²' => 'Meter Persegi (m²)',
                        'ha' => 'Hektar (ha)',
                        'ft²' => 'Kaki Persegi (ft²)',
                    ])
                    ->default('m²')
                    ->required(),
                Select::make('district_id')
                    ->label('Kecamatan')
                    ->relationship('district', 'name')
                    ->reactive()
                    ->afterStateUpdated(fn($state, callable $set) => $set('kelurahan_id', null))
                    ->required(),
                Select::make('kelurahan_id')
                    ->label('Kelurahan')
                    ->options(function (callable $get) {
                        $districtId = $get('district_id'); // Mengambil district yang dipilih

                        return Kelurahan::where('districts_id', $districtId)->pluck('name', 'id');
                    })
                    ->afterStateUpdated(function (Set $set, ?string $state): void {

                        if ($state) {
                            $kelurahan = Kelurahan::find($state)->toArray();
                            // dd($kelurahan);
                            if ($kelurahan) {
                                $set('location', ['latitude' =>  $kelurahan['latitude'], 'longitude' => $kelurahan['longitude']]);
                                $set('latitude',  $kelurahan['latitude']);
                                $set('longitude', $kelurahan['longitude']);
                                // $this->emitMapCoordinates($kelurahan->latitude, $kelurahan->longitude);
                            }
                        }
                    })
                    ->reactive()
                    ->disabled(fn(callable $get) => empty($get('district_id'))),
                Textarea::make('address')
                    ->label('Alama Lengkap')
                    ->columnSpan('full')
                    ->required(),
                Textarea::make('description')
                    ->label('Deskripsi')
                    ->columnSpan('full')
                    ->rows(4)
                    ->reactive(),
                // Tambahkan Map Picker untuk mendapatkan koordinat
                Map::make('location')
                    ->label('Location')
                    ->columnSpanFull()
                    ->defaultLocation(latitude: -6.178306, longitude: 106.631889)

                    ->afterStateUpdated(function ($state, callable $set) {
                        if (isset($state['lat']) && isset($state['lng'])) {
                            $set('latitude', $state['lat']);
                            $set('longitude', $state['lng']);
                        }
                    })

                    ->extraStyles([
                        'min-height: 50vh',
                        'border-radius: 50px'
                    ])
                    ->liveLocation(true, true, 5000)
                    ->showMarker()
                    ->markerColor("#22c55eff")
                    ->showFullscreenControl()
                    ->showZoomControl()
                    ->draggable()
                    ->tilesUrl("https://tile.openstreetmap.de/{z}/{x}/{y}.png")
                    ->zoom(15)
                    ->detectRetina()
                    ->geoMan(true)
                    ->geoManEditable(true)
                    ->geoManPosition('topleft')
                    ->rotateMode()
                    ->dragMode()
                    ->setColor('#3388ff')
                    ->setFilledColor('#cad9ec'),
                TextInput::make('latitude')
                    ->label('Latitude')
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {

                        $set('location', ['lat' => $state, 'lng']);
                    }), // Agar form sinkron dengan peta
                TextInput::make('longitude')
                    ->label('Longitude')
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $set('location', ['lng' => $state]);
                    }),

                SpatieMediaLibraryFileUpload::make('files')
                    ->label('Kondisi Bangunan')
                    ->collection('files') // Menggunakan koleksi 'images'
                    ->multiple() // Mengizinkan multiple file upload
                    ->maxFiles(5) // Membatasi hingga 5 file
                    ->enableReordering() // Opsi ini untuk mengizinkan reordering gambar
                    ->columnSpan('full'),


            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->sortable()->searchable(),
                TextColumn::make('district.name')->label('Kecamatan')->sortable()->searchable(),
                TextColumn::make('address')->label('Alamat')->sortable()->searchable(),
                TextColumn::make('facilityType.name')->label('Jenis Olahraga')->sortable()->searchable(),
                BooleanColumn::make('is_government_owned')->label('Milik Pemerintah')->sortable(),
                BooleanColumn::make('is_private_owned')->label('Milik Swasta')->sortable(),
                TextColumn::make('capacity')->label('Kapasitas')->sortable()->searchable(),
                TextColumn::make('employees_count')->label('Jumlah Karyawan')->sortable()->searchable(),
                TextColumn::make('land_area')
                    ->label('Luas Lahan')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('unit')
                    ->label('Satuan')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('luas_bangunan')
                    ->label('Luas Bangunan')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('unit_luas_bangunan')
                    ->label('Satuan Luas Bangunan')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('latitude')->label('Latitude'),
                TextColumn::make('longitude')->label('Longitude'),


            ])
            ->filters([
                SelectFilter::make('district_id')
                    ->label('Kecamatan')
                    ->options(
                        District::query()
                            ->select('id', 'name')
                            ->distinct()
                            ->pluck('name', 'id')
                            ->toArray()
                    )
                    ->relationship('district', 'name')
                    ->placeholder('All Kecamatan'),
                // You can add more filters if necessary
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->actions([
                Action::make('files')
                    ->label('Foto Kondisi Bangunan')
                    ->icon('heroicon-o-photo')
                    ->action(function ($record, $livewire) {
                        // Emit event to open modal with images
                        $livewire->emit('openImagesModal', $record->id);
                    })
                    ->modalHeading('Foto Kondisi Bangunan')
                    ->modalContent(fn($record) => view('components.image-modal', [
                        'images' => $record->getMedia('files')->map(fn($media) => $media->getUrl()),
                    ])),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSportsFacilities::route('/'),
            'create' => Pages\CreateSportsFacility::route('/create'),
            'edit' => Pages\EditSportsFacility::route('/{record}/edit'),
        ];
    }


    public function setLatLong($latitude, $longitude)
    {
        $this->form->fill([
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    public function updateMap($coordinates)
    {
        $this->form->fill([
            'location' => [
                'lat' => $coordinates['lat'],
                'lng' => $coordinates['lng'],
            ],
        ]);
    }
}
