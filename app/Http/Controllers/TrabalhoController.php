<?php

namespace App\Http\Controllers;

use App\Trabalho;
use App\Coautor;
use App\Evento;
use App\User;
use App\AreaModalidade;
use App\Area;
use App\Revisor;
use App\Modalidade;
use App\Atribuicao;
use App\Arquivo;
use Carbon\Carbon;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Mail\EmailParaUsuarioNaoCadastrado;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TrabalhoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id)
    {
        $evento = Evento::find($id);
        $areas = Area::where('eventoId', $evento->id)->get();
        $areasId = Area::where('eventoId', $evento->id)->select('id')->get();
        $revisores = Revisor::where('eventoId', $evento->id)->get();
        $modalidades = Modalidade::all();
        $areaModalidades = AreaModalidade::whereIn('id', $areasId)->get();
        $areasEnomes = Area::wherein('id', $areasId)->get();
        $modalidadesIDeNome = [];
        foreach ($areaModalidades as $key) {
          array_push($modalidadesIDeNome,['areaId' => $key->area->id,
                                          'modalidadeId' => $key->modalidade->id,
                                          'modalidadeNome' => $key->modalidade->nome]);
        }

        $trabalhos = Trabalho::where('autorId', Auth::user()->id)->whereIn('areaId', $areasId)->get();
        // dd($evento);
        return view('evento.submeterTrabalho',[
                                              'evento'          => $evento,
                                              'areas'           => $areas,
                                              'revisores'       => $revisores,
                                              'modalidades'     => $modalidades,
                                              'areaModalidades' => $areaModalidades,
                                              'trabalhos'       => $trabalhos,
                                              'areasEnomes'     => $areasEnomes,
                                              'modalidadesIDeNome'     => $modalidadesIDeNome,
                                            ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request){
      $validatedData = $request->validate([
        'nomeTrabalho'      => ['required', 'string',],
        'areaId'            => ['required', 'integer'],
        'modalidadeId'      => ['required', 'integer'],
        'eventoId'          => ['required', 'integer'],
        'resumo'            => ['string'],
        'nomeCoautor.*'     => ['string'],
        'emailCoautor.*'    => ['string'],
        'arquivo'           => ['required', 'file', 'mimes:pdf', 'max:2000000'],
      ]);

      $mytime = Carbon::now('America/Recife');
      $mytime = $mytime->toDateString();

      $autor = Auth::user();
      $evento = Evento::find($request->eventoId);
      $trabalhosDoAutor = Trabalho::where('eventoId', $request->eventoId)->where('autorId', Auth::user()->id)->count();
      $areaModalidade = AreaModalidade::where('areaId', $request->areaId)->where('modalidadeId', $request->modalidadeId)->first();

      if($trabalhosDoAutor >= $evento->numMaxTrabalhos){
        return redirect()->back()->withErrors(['numeroMax' => 'Número máximo de trabalhos permitidos atingido.']);
      }

      if($request->emailCoautor != null){
        $i = 0;
        foreach ($request->emailCoautor as $key) {
          $i++;
        }
        if($i > $evento->numMaxCoautores){
          return redirect()->back()->withErrors(['numeroMax' => 'Número de coautores deve ser menor igual a '.$evento->numMaxCoautores]);
        }
      }

      if($request->emailCoautor != null){
        $i = 0;
        foreach ($request->emailCoautor as $key) {
          $userCoautor = User::where('email', $key)->first();
          if($userCoautor == null){
            $passwordTemporario = Str::random(8);
            Mail::to($key)->send(new EmailParaUsuarioNaoCadastrado(Auth()->user()->name, '  ', 'Coautor', $evento->nome, $passwordTemporario));
            $usuario = User::create([
              'email' => $key,
              'password' => bcrypt($passwordTemporario),
              'usuarioTemp' => true,
              'name' => $request->nomeCoautor[$i],
            ]);
          }
          $i++;
        }
      }

      $trabalho = Trabalho::create([
        'titulo' => $request->nomeTrabalho,
        'modalidadeId'  => $areaModalidade->modalidade->id,
        'areaId'  => $areaModalidade->area->id,
        'autorId' => $autor->id,
        'eventoId'  => $evento->id,
      ]);

      if($request->emailCoautor != null){
        foreach ($request->emailCoautor as $key) {
          $userCoautor = User::where('email', $key)->first();
          Coautor::create([
            'ordem' => '-',
            'autorId' => $userCoautor->id,
            'trabalhoId'  => $trabalho->id,
          ]);
        }
      }

      $file = $request->arquivo;
      $path = 'trabalhos/' . $request->eventoId . '/' . $trabalho->id .'/';
      $nome = "1.pdf";
      Storage::putFileAs($path, $file, $nome);

      $arquivo = Arquivo::create([
        'nome'  => $path . $nome,
        'trabalhoId'  => $trabalho->id,
        'versaoFinal' => true,
      ]);

      return redirect()->route('evento.visualizar',['id'=>$request->eventoId]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Trabalho  $trabalho
     * @return \Illuminate\Http\Response
     */
    public function show(Trabalho $trabalho)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Trabalho  $trabalho
     * @return \Illuminate\Http\Response
     */
    public function edit(Trabalho $trabalho)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Trabalho  $trabalho
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Trabalho $trabalho)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Trabalho  $trabalho
     * @return \Illuminate\Http\Response
     */
    public function destroy(Trabalho $trabalho)
    {
        //
    }

    public function novaVersao(Request $request){
      $validatedData = $request->validate([
        'arquivo' => ['required', 'file', 'mimes:pdf'],
        'eventoId' => ['required', 'integer'],
        'trabalhoId' => ['required', 'integer'],
      ]);

      $trabalho = Trabalho::find($request->trabalhoId);

      if(Auth::user()->id != $trabalho->autorId){
        return redirect()->route('home');
      }

      $arquivos = $trabalho->arquivo;
      $count = 1;
      foreach ($arquivos as $key) {
        $key->versaoFinal = false;
        $key->save();
        $count++;
      }

      $file = $request->arquivo;
      $path = 'trabalhos/' . $request->eventoId . '/' . $trabalho->id .'/';
      $nome = $count . ".pdf";
      Storage::putFileAs($path, $file, $nome);

      $arquivo = Arquivo::create([
        'nome'  => $path . $nome,
        'trabalhoId'  => $trabalho->id,
        'versaoFinal' => true,
      ]);

      return redirect()->route('evento.visualizar',['id'=>$request->eventoId]);
    }

    public function detalhesAjax(Request $request){
      $validatedData = $request->validate([
        'trabalhoId' => ['required', 'integer']
      ]);

      $trabalho = Trabalho::find($request->trabalhoId);
      $revisores = Atribuicao::where('trabalhoId', $request->trabalhoId)->get();
      $revisoresAux = [];
      foreach ($revisores as $key) {
        array_push($revisoresAux, [
          'id' => $key->revisor->user->id,
          'nome'  => $key->revisor->user->name
        ]);
      }


      return response()->json([
                               'titulo' => $trabalho->titulo,
                               'resumo'  => $trabalho->resumo,
                               'revisores' => $revisoresAux
                              ], 200);
    }

}
